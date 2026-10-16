@php
    $heroClimate = \App\Support\ClimateSnapshot::latest();
    $aboutPhoto = asset('images/rice-about-farmer.jpg');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_' , '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>{{ config('app.name', 'iClimate') }} | Weather Impact Analysis for Lian, Batangas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  @vite(['resources/css/pages/welcome.css', 'resources/css/accessibility.css', 'resources/js/landing.js'])
  @include('partials.global-loader')
</head>
<body class="lp">
<x-flash-toast />
<a class="lp-skip" href="#main">Skip to main content</a>

@include('partials.gov-strip')

<!-- NAVBAR -->
<header class="lp-nav" id="navbar">
  <div class="lp-wrap lp-nav-inner">
    <a href="{{ url('/') }}" class="lp-nav-logo">
      <img src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate home" width="120" height="44">
    </a>
    <nav aria-label="Main">
      <ul class="lp-nav-links" id="navLinks">
        <li><a href="#home" class="nav-link active">Home</a></li>
        <li><a href="#about" class="nav-link">About</a></li>
        <li><a href="#features" class="nav-link">Features</a></li>
        <li><a href="#roles" class="nav-link">User Roles</a></li>
        <li><a href="#contact" class="nav-link">Contact</a></li>
        <li class="lp-nav-links-account"><a href="{{ route('login') }}">Log in</a></li>
        <li class="lp-nav-links-account"><a href="{{ route('register') }}">Create account</a></li>
      </ul>
    </nav>
    <div class="lp-nav-actions">
      <a href="{{ route('login') }}" class="lp-nav-login">Log in</a>
      <a href="{{ route('register') }}" class="lp-btn lp-btn--primary lp-btn--sm">Create account</a>
      <button type="button" class="lp-hamburger" id="hamburger" aria-label="Open menu" aria-expanded="false" aria-controls="navLinks">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<main id="main">

<!-- HERO -->
<section class="lp-hero" id="home">
  <div class="lp-wrap lp-hero-inner">
    <div class="lp-hero-text">
      <h1 class="lp-hero-title">Climate-smart rice farming for Lian.</h1>
      <p class="lp-hero-desc">Weather, planting advice and rice yield forecasts for the farmers of Lian, Batangas. All in one place.</p>
      <div class="lp-hero-actions">
        <a href="{{ route('login') }}" class="lp-btn lp-btn--primary">
          Get started
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <a href="#about" class="lp-btn lp-btn--outline">Learn more</a>
      </div>
    </div>

    <div class="lp-conditions">
      <div class="lp-conditions-head">
        <h2 class="lp-conditions-title">Current conditions in Lian</h2>
        <p class="lp-conditions-status" data-hero-live>{{ $heroClimate ? 'Latest saved climate record' : 'Checking live weather…' }}</p>
      </div>
      <div class="lp-map-panel">
        <div class="lp-map" id="heroMap" data-lat="14.0381" data-lng="120.6522" data-boundary="{{ asset('geojson/lian-boundary.geojson') }}" aria-label="Map of Lian, Batangas showing the Municipal Agriculture Office"></div>
        <a class="lp-map-link" href="https://www.google.com/maps/search/?api=1&amp;query=14.0381%2C120.6522" target="_blank" rel="noopener noreferrer" aria-label="Open the Municipal Agriculture Office of Lian, Batangas in Google Maps (opens in a new tab)">
          <span class="lp-map-open">Open in Google Maps <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M4.5 2H2v8h8V7.5M7 2h3v3M10 2 5.5 6.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        </a>
        <div class="lp-map-card" id="heroMapCard">
          <div class="lp-map-card-office">Municipal Agriculture Office</div>
          <div class="lp-map-card-place">Poblacion, Lian, Batangas</div>
        </div>
      </div>
      <dl class="lp-weather">
        <div class="lp-weather-item">
          <dt>Temperature</dt>
          <dd data-hero-temp>{{ $heroClimate ? round((float) $heroClimate->temperature).'°C' : '--' }}</dd>
        </div>
        <div class="lp-weather-item">
          <dt>Humidity</dt>
          <dd data-hero-humidity>{{ $heroClimate ? round((float) $heroClimate->humidity).'%' : '--' }}</dd>
        </div>
        <div class="lp-weather-item">
          {{-- This wind figure is from a stored historical ClimateRecord, whose wind
               unit is not established by any repository evidence, so no unit is
               asserted here (unlike genuinely-km/h live Open-Meteo readings). --}}
          <dt>Wind speed</dt>
          <dd data-hero-wind>{{ $heroClimate ? round((float) $heroClimate->wind_speed) : '--' }}</dd>
        </div>
        <div class="lp-weather-item">
          <dt data-hero-rain-label>{{ $heroClimate ? 'Rainfall (record)' : 'Rainfall today' }}</dt>
          <dd data-hero-rain>{{ $heroClimate ? number_format((float) $heroClimate->rainfall, 1).' mm' : '--' }}</dd>
        </div>
      </dl>
      <p class="lp-map-credit">Tiles &copy; <a href="https://www.esri.com/" target="_blank" rel="noopener noreferrer">Esri</a> &middot; Sources: Esri, HERE, Garmin, USGS, <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors and the GIS User Community</p>
    </div>
  </div>
</section>

<!-- SOURCES -->
<section class="lp-sources" aria-label="Where the information comes from">
  <div class="lp-wrap lp-sources-inner">
    <p class="lp-sources-label">Information drawn from</p>
    <ul class="lp-sources-list">
      <li>PAGASA advisories</li>
      <li>Open-Meteo forecasts</li>
      <li>Stored climate records</li>
      <li>MAO rice production records</li>
    </ul>
  </div>
</section>

<!-- ABOUT -->
<section class="lp-section lp-about" id="about">
  <div class="lp-wrap">
    <div class="lp-about-grid">
      <figure class="lp-about-photo">
        <img src="{{ $aboutPhoto }}" alt="A farmer transplanting rice seedlings in a flooded paddy" loading="lazy" width="1400" height="787">
      </figure>
      <div class="lp-about-text">
        <h2 class="lp-h2">Understand today. Prepare tomorrow.</h2>
        <p>iClimate is a web-based decision support system that combines climate data and local rice production records to help rice farmers, MAO staff with technicians, and IT personnel make informed decisions for better agricultural outcomes in Lian, Batangas.</p>
        <blockquote class="lp-mission">
          <p class="lp-mission-label">Our mission</p>
          <p>To empower the farming community of Lian, Batangas through accurate climate information, clear analysis, and practical recommendations.</p>
        </blockquote>
      </div>
    </div>

    <ul class="lp-pillars">
      <li class="lp-pillar">
        <span class="lp-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 20 20" fill="none"><path d="M2 12c1.5-3.5 4-5 8-5s6.5 1.5 8 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="10" cy="5.5" r="1.6" fill="currentColor"/></svg></span>
        <h3>Climate-informed</h3>
        <p>Live weather forecasts, stored climate records and official PAGASA advisories, combined into guidance for Lian's rice fields.</p>
      </li>
      <li class="lp-pillar">
        <span class="lp-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 20 20" fill="none"><path d="M10 2c2.2 3 4 5.3 4 7.5A4 4 0 1 1 6 9.5C6 7.3 7.8 5 10 2z" stroke="currentColor" stroke-width="1.5"/></svg></span>
        <h3>Rice-focused</h3>
        <p>Specialized in rice production analysis, yield prediction, and planting recommendations.</p>
      </li>
      <li class="lp-pillar">
        <span class="lp-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 20 20" fill="none"><rect x="3" y="10" width="3" height="7" fill="currentColor"/><rect x="8.5" y="6" width="3" height="11" fill="currentColor" opacity="0.8"/><rect x="14" y="3" width="3" height="14" fill="currentColor" opacity="0.6"/></svg></span>
        <h3>Based on records</h3>
        <p>Uses historical records and analysis to produce accurate insights and reports.</p>
      </li>
      <li class="lp-pillar">
        <span class="lp-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 20 20" fill="none"><circle cx="7" cy="7" r="2.6" stroke="currentColor" stroke-width="1.4"/><circle cx="14" cy="8" r="2.2" stroke="currentColor" stroke-width="1.4"/><path d="M2.5 16c0.5-3 2-4.5 4.5-4.5s4 1.5 4.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M11.5 16c0.4-2.4 1.7-3.7 3.5-3.7s3 1.1 3.5 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></span>
        <h3>Community-centered</h3>
        <p>Built for farmers, supported by MAO staff and technicians, and maintained by IT personnel.</p>
      </li>
      <li class="lp-pillar">
        <span class="lp-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 20 20" fill="none"><path d="M10 2l7 2.5v5c0 4.5-3 7.5-7 8.5-4-1-7-4-7-8.5v-5L10 2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg></span>
        <h3>Secure and reliable</h3>
        <p>Keeps records safe, the system dependable, and improvements ongoing.</p>
      </li>
    </ul>
  </div>
</section>

<!-- FEATURES -->
<section class="lp-section lp-features" id="features">
  <div class="lp-wrap">
    <div class="lp-section-head">
      <h2 class="lp-h2">Tools for the rice season</h2>
      <p class="lp-lede">Practical information to help you plan, monitor, and decide from planting through harvest.</p>
    </div>

    <ul class="lp-feature-grid">
      <li class="lp-feature">
        <span class="lp-icon lp-icon--solid" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M2 13C3.5 9 6.5 7 11 7s7.5 2 9 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M5 17c1-2.5 3-4 6-4s5 1.5 6 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="11" cy="4.5" r="2" fill="currentColor"/></svg></span>
        <h3>Weather analysis</h3>
        <p>Access real-time and historical climate data including rainfall, temperature, humidity, wind, and more from PAGASA.</p>
        <span class="lp-tag">Climate records</span>
      </li>
      <li class="lp-feature">
        <span class="lp-icon lp-icon--solid" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 22 22" fill="none"><rect x="2" y="14" width="4" height="6" rx="1" fill="currentColor"/><rect x="9" y="9" width="4" height="11" rx="1" fill="currentColor" opacity="0.8"/><rect x="16" y="5" width="4" height="15" rx="1" fill="currentColor" opacity="0.6"/></svg></span>
        <h3>Rice production</h3>
        <p>View and analyze local rice production records and forecasts to support planning and food security.</p>
        <span class="lp-tag">Records</span>
      </li>
      <li class="lp-feature">
        <span class="lp-icon lp-icon--solid" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 22 22" fill="none"><rect x="3" y="4.5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M3 9h16M7.5 2.5v4M14.5 2.5v4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M8 13.5l2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        <h3>Planting advisory</h3>
        <p>Get planting recommendations based on season, rainfall outlook, and soil conditions.</p>
        <span class="lp-tag">Advisory</span>
      </li>
      <li class="lp-feature">
        <span class="lp-icon lp-icon--solid" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M2 16l5-6 4 3.5L15 7l5 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 20h18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
        <h3>Climate monitoring</h3>
        <p>Monitor long-term climate trends and detect unusual patterns that affect farming and productivity.</p>
        <span class="lp-tag">Monitoring</span>
      </li>
      <li class="lp-feature">
        <span class="lp-icon lp-icon--solid" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M3 5.5l5-2 6 2 5-2v13l-5 2-6-2-5 2v-13z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 3.5v13M14 5.5v13" stroke="currentColor" stroke-width="1.5"/></svg></span>
        <h3>Heat map</h3>
        <p>See flood, drought, typhoon, and heat risk levels across barangays on interactive maps.</p>
        <span class="lp-tag">Risk records</span>
      </li>
      <li class="lp-feature">
        <span class="lp-icon lp-icon--solid" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M4 3h10l4 4v12H4V3z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 3v4h4" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M7 11h8M7 14h6M7 17h7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></span>
        <h3>Reports and analytics</h3>
        <p>Generate and download seasonal reports, yield forecasts, and summaries for planning.</p>
        <span class="lp-tag">Analytics</span>
      </li>
    </ul>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="lp-steps" aria-labelledby="stepsTitle">
  <div class="lp-wrap">
    <div class="lp-section-head">
      <h2 class="lp-h2" id="stepsTitle">From records to harvest in four steps</h2>
    </div>
    <ol class="lp-step-list">
      <li class="lp-step">
        <span class="lp-step-num" aria-hidden="true">1</span>
        <h3>Log climate data</h3>
        <p>We collect real-time and historical climate data from trusted sources like PAGASA.</p>
      </li>
      <li class="lp-step">
        <span class="lp-step-num" aria-hidden="true">2</span>
        <h3>Get advisories</h3>
        <p>The system reviews the data to provide planting and fertilizer recommendations.</p>
      </li>
      <li class="lp-step">
        <span class="lp-step-num" aria-hidden="true">3</span>
        <h3>Monitor risk</h3>
        <p>We track weather risks and climate patterns that may affect your crops.</p>
      </li>
      <li class="lp-step">
        <span class="lp-step-num" aria-hidden="true">4</span>
        <h3>Report outcomes</h3>
        <p>Generate reports and yield forecasts to guide better farming decisions.</p>
      </li>
    </ol>
  </div>
</section>

<!-- USER ROLES -->
<section class="lp-section lp-roles" id="roles">
  <div class="lp-wrap">
    <div class="lp-section-head">
      <h2 class="lp-h2">Who uses iClimate?</h2>
      <p class="lp-lede">Information for rice farmers, MAO personnel, and IT support staff, with each role seeing the work it needs.</p>
    </div>

    @php
        $roles = [
            [
                'label' => 'Workspace 1',
                'title' => 'Rice Farmer',
                'text' => 'View climate conditions, receive planting advisories, check weather risk alerts, and access seasonal information through a simple farmer-friendly dashboard.',
                'features' => ['Climate monitoring', 'Planting guidance', 'Weather risk alerts', 'Notifications', 'Seasonal reports'],
                'icon' => '<circle cx="10" cy="6.5" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M3.5 18c.6-4.8 3-7.5 6.5-7.5s5.9 2.7 6.5 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
            ],
            [
                'label' => 'Workspace 2',
                'title' => 'MAO Personnel',
                'text' => 'Manage agricultural records, monitor rice production trends, analyze climate-yield relationships, and generate reports to support farmers and municipal planning.',
                'features' => ['Production monitoring', 'Climate analytics', 'Report generation', 'Record validation', 'Heat map monitoring'],
                'icon' => '<path d="M3 8l7-5 7 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 8v9h12V8" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8.5 17v-5h3v5" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>',
            ],
            [
                'label' => 'Workspace 3',
                'title' => 'IT Personnel',
                'text' => 'Support user access, keep records organized, monitor system activity, and help the MAO team keep iClimate secure and dependable.',
                'features' => ['User access support', 'Activity checks', 'Record upkeep', 'Account support', 'System reliability'],
                'icon' => '<rect x="5" y="9" width="10" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M7 9V6a3 3 0 0 1 6 0v3" stroke="currentColor" stroke-width="1.5"/>',
            ],
        ];
    @endphp
    <ul class="lp-role-grid">
      @foreach ($roles as $role)
        <li class="lp-role">
          <div class="lp-role-top">
            <span class="lp-icon lp-icon--lg" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 20 20" fill="none">{!! $role['icon'] !!}</svg></span>
            <span class="lp-role-label">{{ $role['label'] }}</span>
          </div>
          <h3>{{ $role['title'] }}</h3>
          <p>{{ $role['text'] }}</p>
          <p class="lp-role-features-label">Key features</p>
          <ul class="lp-role-features">
            @foreach ($role['features'] as $feature)
              <li>
                <svg width="18" height="18" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3.5 8.5l3 3 6-6.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ $feature }}
              </li>
            @endforeach
          </ul>
          <a href="{{ route('login') }}" class="lp-btn lp-btn--outline lp-btn--block">
            View dashboard
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        </li>
      @endforeach
    </ul>
  </div>
</section>

<!-- CONTACT -->
<section class="lp-section lp-contact" id="contact">
  <div class="lp-wrap">
    <div class="lp-contact-grid">
      <div class="lp-contact-info">
        <h2 class="lp-h2">Have questions about iClimate?</h2>
        <p class="lp-contact-lede">Reach the team at the Municipal Agriculture Office of Lian, Batangas. We support farmers, MAO personnel, and IT staff using the platform.</p>

        <dl class="lp-contact-list">
          <div class="lp-contact-item">
            <dt>
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M9 16s5.5-5.1 5.5-9A5.5 5.5 0 0 0 3.5 7c0 3.9 5.5 9 5.5 9Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="9" cy="7" r="2" stroke="currentColor" stroke-width="1.5"/></svg>
              Address
            </dt>
            <dd>
              Municipal Agriculture Office, Lian, Batangas, Philippines<br>
              <a href="https://www.google.com/maps/dir/?api=1&amp;destination=14.0381,120.6522" target="_blank" rel="noopener noreferrer">Get directions</a>
            </dd>
          </div>
          <div class="lp-contact-item">
            <dt>
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><rect x="2" y="4" width="14" height="10" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M2.5 5l6.5 5 6.5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Email
            </dt>
            <dd><a href="mailto:mao@lianbatangas.gov.ph">mao@lianbatangas.gov.ph</a></dd>
          </div>
          <div class="lp-contact-item">
            <dt>
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M3.5 2.5h3l1.5 4-2 1.3a9 9 0 0 0 4.2 4.2l1.3-2 4 1.5v3a1.5 1.5 0 0 1-1.6 1.5A13.5 13.5 0 0 1 2 4.1 1.5 1.5 0 0 1 3.5 2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Phone
            </dt>
            <dd><a href="tel:+639171234567">+63 917 123 4567</a></dd>
          </div>
          <div class="lp-contact-item">
            <dt>
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><circle cx="9" cy="9" r="6.5" stroke="currentColor" stroke-width="1.5"/><path d="M9 5.5V9l3 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Office hours
            </dt>
            <dd>
              <table class="lp-hours">
                <tr><th scope="row">Monday to Friday</th><td>8:00 AM to 5:00 PM</td></tr>
                <tr><th scope="row">Saturday</th><td>By appointment</td></tr>
                <tr><th scope="row">Sunday</th><td>Closed</td></tr>
              </table>
            </dd>
          </div>
        </dl>
      </div>

      <form class="lp-form" id="contactForm" method="POST" action="{{ route('contact.send') }}" novalidate>
        @csrf
        <h3 class="lp-form-title">Send us a message</h3>
        <p class="lp-form-sub">We typically respond within one business day.</p>
        <div class="lp-field">
          <label for="contactName">Full name</label>
          <input type="text" id="contactName" name="name" maxlength="120" autocomplete="name" required placeholder="Juan Dela Cruz"/>
        </div>
        <div class="lp-field-row">
          <div class="lp-field">
            <label for="contactEmail">Email address</label>
            <input type="email" id="contactEmail" name="email" maxlength="255" autocomplete="email" required placeholder="juan@gmail.com"/>
          </div>
          <div class="lp-field">
            <label for="contactRole">Role</label>
            <select id="contactRole" name="role">
              <option value="" selected disabled>Select your role</option>
              <option>Rice farmer</option>
              <option>MAO personnel</option>
              <option>IT personnel</option>
              <option>Other</option>
            </select>
          </div>
        </div>
        <div class="lp-field">
          <label for="contactMessage">Message</label>
          <textarea id="contactMessage" name="message" rows="4" maxlength="2000" required placeholder="How can we help you?"></textarea>
        </div>
        {{-- Left empty by people; automated form fillers complete it and are refused. --}}
        <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="lp-honeypot">
        <p id="contactStatus" class="lp-form-status" role="status" aria-live="polite" hidden></p>
        <button type="submit" class="lp-btn lp-btn--primary lp-btn--block" id="contactSubmit">
          Send message
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </form>
    </div>
  </div>
</section>

</main>

<!-- FOOTER -->
<footer class="lp-footer">
  <div class="lp-wrap">
    <div class="lp-ft-top">
      <div class="lp-ft-brand">
        <a href="{{ url('/') }}" class="lp-ft-logo" aria-label="iClimate home">
          <img src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate" width="130" height="48">
        </a>
        <p>Weather, planting advice and rice yield forecasts for the farmers of Lian, Batangas.</p>
        <div class="lp-ft-social">
          <a href="https://www.facebook.com/profile.php?id=61551743430540" class="lp-ft-icon" target="_blank" rel="noopener noreferrer" aria-label="iClimate on Facebook (opens in a new tab)" title="Facebook"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-7.5h2.5l.4-3H13.5V8.5c0-.9.2-1.5 1.5-1.5h1.6V4.3C16.3 4.2 15.3 4 14.1 4c-2.4 0-4.1 1.5-4.1 4.2V10.5H7.5v3H10V21h3.5z"/></svg></a>
          <div class="lp-ft-email" data-email-menu data-email="mao_lian@yahoo.com">
            <a href="mailto:mao_lian@yahoo.com" class="lp-ft-icon" aria-label="Email iClimate at mao_lian@yahoo.com" title="Email us: mao_lian@yahoo.com" aria-haspopup="true" aria-expanded="false" data-email-trigger>
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><rect x="2" y="4" width="14" height="10" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M2.5 5l6.5 5 6.5-5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
            <div class="lp-email-menu" role="menu" hidden data-email-panel>
              <div class="lp-email-addr">mao_lian@yahoo.com</div>
              <a role="menuitem" target="_blank" rel="noopener noreferrer" href="https://mail.google.com/mail/?view=cm&amp;fs=1&amp;to=mao_lian@yahoo.com">Write in Gmail</a>
              <a role="menuitem" target="_blank" rel="noopener noreferrer" href="https://compose.mail.yahoo.com/?to=mao_lian@yahoo.com">Write in Yahoo Mail</a>
              <a role="menuitem" href="mailto:mao_lian@yahoo.com">Use my email app</a>
              <button type="button" role="menuitem" data-email-copy>Copy address</button>
            </div>
          </div>
        </div>
      </div>

      <nav class="lp-ft-cols" aria-label="Footer">
        <div class="lp-ft-col">
          <h2 class="lp-ft-head">Explore</h2>
          <ul>
            <li><a href="#about">About</a></li>
            <li><a href="#features">Features</a></li>
            <li><a href="#roles">User roles</a></li>
            <li><a href="#contact">Contact</a></li>
          </ul>
        </div>
        <div class="lp-ft-col">
          <h2 class="lp-ft-head">Account</h2>
          <ul>
            <li><a href="{{ route('login') }}">Log in</a></li>
            <li><a href="{{ route('register') }}">Create account</a></li>
            <li><a href="{{ route('password.request') }}">Forgot password</a></li>
          </ul>
        </div>
        <div class="lp-ft-col">
          <h2 class="lp-ft-head">Visit us</h2>
          <ul>
            <li>Municipal Agriculture Office</li>
            <li>Lian, Batangas, Philippines</li>
            <li>Mon to Fri, 8 AM to 5 PM</li>
            <li><a href="https://www.google.com/maps/dir/?api=1&amp;destination=14.0381,120.6522" target="_blank" rel="noopener noreferrer">Get directions</a></li>
          </ul>
        </div>
      </nav>
    </div>

    <div class="lp-ft-bottom">
      <p>&copy; 2026 iClimate Research Group &middot; Batangas State University ARASOF-Nasugbu</p>
      <a href="#home" class="lp-ft-top-link">
        Back to top
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M6 10V2M2.5 5.5 6 2l3.5 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    </div>
  </div>
</footer>

<script>
  // Email icon: a mailto: link does nothing when the visitor has no desktop
  // mail app configured, so offer webmail, the mail app, or copying instead.
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-email-menu]').forEach((wrap) => {
      const trigger = wrap.querySelector('[data-email-trigger]');
      const panel = wrap.querySelector('[data-email-panel]');
      const copyBtn = wrap.querySelector('[data-email-copy]');
      const email = wrap.dataset.email;
      // The footer clips overflow, so the panel lives on <body> and is
      // positioned beside the icon, opening wherever there is room.
      document.body.appendChild(panel);
      const place = () => {
        const r = trigger.getBoundingClientRect();
        const w = panel.offsetWidth;
        const h = panel.offsetHeight;
        const gap = 10;
        const left = Math.min(Math.max(8, r.left + r.width / 2 - w / 2), window.innerWidth - w - 8);
        const up = window.innerHeight - r.bottom < h + gap + 8 && r.top > h + gap + 8;
        panel.style.left = `${left}px`;
        panel.style.top = `${up ? r.top - h - gap : r.bottom + gap}px`;
      };
      const setOpen = (open) => {
        panel.hidden = !open;
        trigger.setAttribute('aria-expanded', String(open));
        if (open) { place(); panel.querySelector('a, button')?.focus({ preventScroll: true }); }
      };
      window.addEventListener('scroll', () => { if (!panel.hidden) place(); }, { passive: true });
      window.addEventListener('resize', () => { if (!panel.hidden) place(); });
      trigger.addEventListener('click', (event) => {
        event.preventDefault();
        setOpen(panel.hidden);
      });
      panel.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => setOpen(false)));
      copyBtn?.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(email);
        } catch (e) {
          const tmp = document.createElement('textarea');
          tmp.value = email; document.body.appendChild(tmp); tmp.select();
          document.execCommand('copy'); tmp.remove();
        }
        copyBtn.textContent = 'Copied!';
        setTimeout(() => { copyBtn.textContent = 'Copy address'; setOpen(false); }, 1200);
      });
      document.addEventListener('click', (event) => {
        if (!wrap.contains(event.target) && !panel.contains(event.target)) setOpen(false);
      });
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) { setOpen(false); trigger.focus(); }
      });
    });
  });

  // Live Lian weather for the hero card, from the same public endpoint the
  // login page uses. The stored record rendered server-side stays as the
  // fallback when the live service is unavailable.
  document.addEventListener('DOMContentLoaded', async () => {
    const set = (selector, value) => {
      const el = document.querySelector(selector);
      if (el && value !== null && value !== undefined) el.textContent = value;
    };
    try {
      const res = await fetch('{{ route('login.weather') }}', { headers: { Accept: 'application/json' } });
      if (!res.ok) return;
      const w = await res.json();
      if (!w || !w.has_real_weather) return;
      const n = (v, d = 0) => (v === null || v === undefined || Number.isNaN(Number(v)) ? null : Number(v).toFixed(d));
      if (n(w.temperature) !== null) set('[data-hero-temp]', `${n(w.temperature)}°C`);
      if (n(w.humidity) !== null) set('[data-hero-humidity]', `${n(w.humidity)}%`);
      if (n(w.wind_speed) !== null) set('[data-hero-wind]', `${n(w.wind_speed)} km/h`);
      if (n(w.rainfall, 1) !== null) set('[data-hero-rain]', `${n(w.rainfall, 1)} mm`);
      set('[data-hero-rain-label]', 'Rainfall today');
      const live = document.querySelector('[data-hero-live]');
      if (live) {
        live.textContent = `Live now · ${w.condition_label || 'Current weather'}${w.updated_label ? ' · ' + w.updated_label : ''}`;
        live.classList.add('is-live');
      }
    } catch (e) { /* keep the saved record */ }
  });

  document.addEventListener('DOMContentLoaded', () => {
    const navLinks = document.getElementById('navLinks');
    const hamburger = document.getElementById('hamburger');
    const links = document.querySelectorAll('.nav-link');
    const setMenu = (open) => {
      navLinks?.classList.toggle('open', open);
      hamburger?.setAttribute('aria-expanded', String(open));
      hamburger?.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    };

    hamburger?.addEventListener('click', () => setMenu(!navLinks.classList.contains('open')));
    // Highlight the clicked link straight away. While the page scrolls to it, the scroll spy below
    // stays quiet so the sections passed on the way are not highlighted one after another.
    let spyPaused = false;
    let resumeTimer;
    const resumeSpy = () => { spyPaused = false; clearTimeout(resumeTimer); };
    window.addEventListener('scrollend', resumeSpy);
    links.forEach(link => link.addEventListener('click', () => {
      setMenu(false);
      links.forEach(other => other.classList.toggle('active', other === link));
      spyPaused = true;
      clearTimeout(resumeTimer);
      resumeTimer = setTimeout(resumeSpy, 1500); // browsers without the scrollend event
    }));

    const sections = document.querySelectorAll('section[id]');
    const spyObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting || spyPaused) return;
        const id = entry.target.getAttribute('id');
        links.forEach(link => {
          link.classList.toggle('active', link.getAttribute('href') === '#' + id);
        });
      });
    }, { rootMargin: '-40% 0px -55% 0px', threshold: 0 });
    sections.forEach(section => spyObserver.observe(section));

    // "Send us a message": delivered to the MAO staff as a notification, without leaving the page.
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
      const status = document.getElementById('contactStatus');
      const submit = document.getElementById('contactSubmit');
      const say = (text, ok) => { status.textContent = text; status.classList.toggle('is-error', !ok); status.hidden = false; };
      contactForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!contactForm.reportValidity()) return;
        submit.disabled = true;
        say('Sending your message...', true);
        try {
          const response = await fetch(contactForm.action, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(contactForm) });
          const data = await response.json().catch(() => ({}));
          if (response.ok && data.ok) { say(data.message, true); contactForm.reset(); }
          else if (response.status === 422) say(Object.values(data.errors || {})[0]?.[0] || 'Please check what you typed and try again.', false);
          else if (response.status === 429) say('Too many messages were sent from here. Please try again in a few minutes.', false);
          else say(data.message || 'Your message could not be sent. Please call or email the office instead.', false);
        } catch (error) {
          say('Your message could not be sent. Please check your connection and try again.', false);
        } finally {
          submit.disabled = false;
        }
      });
    }
  });
</script>
</body>
</html>
