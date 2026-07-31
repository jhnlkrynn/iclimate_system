@php
    $flashType = session('error') || (isset($errors) && $errors->any())
        ? 'error'
        : (session('success') ? 'success' : (session('status') ? 'info' : null));
    $flashMessage = session('error')
        ?: session('success')
        ?: session('status')
        ?: (isset($errors) && $errors->any() ? $errors->first() : null);
    $flashTitle = match ($flashType) {
        'error' => 'Action failed',
        'success' => 'Action successful',
        'info' => 'Notice',
        default => null,
    };
@endphp
@if($flashType && $flashMessage)
    <div id="flashToastStack" class="flash-toast-stack">
        <div id="flashToast" class="flash-toast {{ $flashType }} show" role="alertdialog" aria-live="polite" aria-labelledby="flashToastTitle">
            <div class="flash-toast-accent" aria-hidden="true"></div>
            <div class="flash-toast-body">
                <div class="flash-toast-icon" aria-hidden="true">{{ $flashType === 'success' ? '✓' : ($flashType === 'error' ? '!' : 'i') }}</div>
                <div class="flash-toast-text">
                    <h2 id="flashToastTitle" class="flash-toast-title">{{ $flashTitle }}</h2>
                    <p class="flash-toast-message">{{ $flashMessage }}</p>
                </div>
                <button type="button" class="flash-toast-close" data-flash-toast-close aria-label="Dismiss">&times;</button>
            </div>
            <div class="flash-toast-timer" aria-hidden="true"><span></span></div>
        </div>
    </div>
    <style>
        .flash-toast-stack { position: fixed; top: 1rem; right: 1rem; z-index: 2400; display: flex; flex-direction: column; gap: .6rem; max-width: min(360px, calc(100vw - 2rem)); }
        .flash-toast { border: 1px solid var(--ic-border, #d4edda); border-radius: 16px; overflow: hidden; background: linear-gradient(145deg, #fff, #f7fbf8); box-shadow: 0 1rem 2.4rem rgba(13,31,24,.22); animation: flashToastIn .22s ease-out; }
        .flash-toast.success { --toast-accent: var(--ic-green-500, #52b788); }
        .flash-toast.error { --toast-accent: var(--ic-coral, #d85b45); }
        .flash-toast.info { --toast-accent: var(--ic-gold, #e8a73d); }
        .flash-toast-accent { height: 4px; background: linear-gradient(90deg, var(--toast-accent), var(--ic-gold, #e8a73d), var(--ic-green-400, #74c69d)); }
        .flash-toast-body { display: grid; grid-template-columns: auto minmax(0,1fr) auto; gap: .65rem; align-items: flex-start; padding: .8rem .85rem; }
        .flash-toast-icon { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; color: #fff; background: var(--toast-accent); font-weight: 900; flex-shrink: 0; }
        .flash-toast-title { margin: 0 0 .2rem; color: var(--ic-ink, #0d1f18); font-weight: 900; font-size: .92rem; }
        .flash-toast-message { margin: 0; color: var(--ic-ink-mid, #3d5a48); line-height: 1.4; font-size: .86rem; }
        .flash-toast-close { border: 0; background: transparent; color: var(--ic-ink-mid, #3d5a48); font-size: 1.1rem; line-height: 1; padding: .1rem .3rem; flex-shrink: 0; cursor: pointer; }
        .flash-toast-close:hover { color: var(--ic-ink, #0d1f18); }
        .flash-toast-timer { height: 3px; background: color-mix(in srgb, var(--toast-accent) 24%, #fff); }
        .flash-toast-timer span { display: block; height: 100%; width: 100%; background: var(--toast-accent); animation: flashToastTimer 4.8s linear forwards; }
        @keyframes flashToastIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes flashToastTimer { from { width: 100%; } to { width: 0%; } }
        @media (max-width: 480px) {
            .flash-toast-stack { top: .6rem; right: .6rem; left: .6rem; max-width: none; }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toast = document.getElementById('flashToast');
            if (!toast) return;
            const close = () => toast.remove();
            toast.querySelector('[data-flash-toast-close]')?.addEventListener('click', close);
            setTimeout(close, 4800);
        });
    </script>
@endif
