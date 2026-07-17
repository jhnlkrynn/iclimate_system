@if ($isFarmer)
    <div class="sidebar-brand sidebar-brand--large p-4 pb-2">
        <img src="{{ asset('images/iclimate-icon.png') }}" alt="iClimate" class="sidebar-logo-large">
        <div class="sidebar-wordmark-lg">iClimate</div>
        <div class="sidebar-brand-underline"></div>
    </div>
@else
    <div class="sidebar-brand p-4 pb-2">
        <div class="sidebar-brand-row">
            <img src="{{ asset('images/iclimate-icon.png') }}" alt="" class="sidebar-logo-icon">
            <span class="sidebar-wordmark">iClimate</span>
        </div>
        <div class="sidebar-brand-underline"></div>
        <div class="sidebar-location"><span class="pulse-dot"></span> Lian, Batangas</div>
        <div class="sidebar-tagline pe-2">Weather Impact and Rice Yield System</div>
    </div>
@endif
