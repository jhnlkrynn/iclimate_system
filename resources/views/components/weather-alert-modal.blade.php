@props(['alert' => null])

@php
    $isActive = is_array($alert) && trim((string) ($alert['message'] ?? '')) !== '';
    $alertId = $isActive ? (string) ($alert['id'] ?? 'alert') : '';
    $alertDate = $isActive ? (string) ($alert['date'] ?? now('Asia/Manila')->toDateString()) : '';
    $alertKey = $isActive ? $alertId.'|'.$alertDate : '';
    $tone = $isActive && in_array($alert['tone'] ?? null, ['danger', 'watch'], true) ? $alert['tone'] : 'danger';
    $icon = $isActive ? (string) ($alert['icon'] ?? 'alert') : 'alert';
    $title = $isActive ? (string) ($alert['title'] ?? __('farmer.home.message.headline_alert')) : '';
    $message = $isActive ? (string) $alert['message'] : '';
@endphp

@if ($isActive)
    <div class="ic-alert-modal is-{{ $tone }}" id="icWeatherAlertModal" data-weather-alert-modal data-alert-key="{{ $alertKey }}" role="dialog" aria-modal="true" aria-labelledby="icWeatherAlertTitle" aria-describedby="icWeatherAlertMessage" hidden>
        <div class="ic-alert-modal-backdrop" data-weather-alert-close></div>
        <div class="ic-alert-modal-panel" tabindex="-1" data-weather-alert-panel>
            <div class="ic-alert-modal-head">
                <div class="ic-alert-modal-head-main">
                    <span class="ic-alert-modal-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $icon])</span>
                    <h2 id="icWeatherAlertTitle" class="ic-alert-modal-title">{{ $title }}</h2>
                </div>
                <button type="button" class="ic-alert-modal-close" data-weather-alert-close aria-label="{{ __('farmer.kpis.detail.close') }}">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
            <div class="ic-alert-modal-body">
                <p id="icWeatherAlertMessage" class="ic-alert-modal-msg">{{ $message }}</p>
                <div class="ic-alert-modal-actions">
                    <button type="button" class="ic-alert-modal-btn" data-weather-alert-close>{{ __('farmer.home.message.got_it') }}</button>
                </div>
            </div>
        </div>
    </div>
    <script @nonce>
        (function () {
            var modal = document.getElementById('icWeatherAlertModal');
            if (!modal) return;
            var storageKey = 'iclimate.weather_alert_dismissed';
            var alertKey = modal.getAttribute('data-alert-key') || '';
            var panel = modal.querySelector('[data-weather-alert-panel]');
            var dismissed = null;
            try {
                dismissed = window.localStorage.getItem(storageKey);
            } catch (e) {}
            if (dismissed && dismissed === alertKey) {
                return;
            }
            document.body.appendChild(modal);
            var closeModal = function () {
                try {
                    if (alertKey) window.localStorage.setItem(storageKey, alertKey);
                } catch (e) {}
                modal.classList.remove('is-open');
                modal.hidden = true;
                document.documentElement.classList.remove('ic-alert-modal-lock');
            };
            modal.querySelectorAll('[data-weather-alert-close]').forEach(function (el) {
                el.addEventListener('click', closeModal);
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.hidden) {
                    closeModal();
                }
            });
            modal.hidden = false;
            document.documentElement.classList.add('ic-alert-modal-lock');
            requestAnimationFrame(function () {
                modal.classList.add('is-open');
                if (panel) panel.focus({ preventScroll: true });
            });
        })();
    </script>
@endif
