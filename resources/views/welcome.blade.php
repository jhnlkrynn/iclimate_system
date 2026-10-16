@php
    $heroClimate = \App\Support\ClimateSnapshot::latest();
    $rice = [
        'hero'     => asset('images/rice-hero-aerial.jpg'),
        'about'    => asset('images/rice-about-farmer.jpg'),
        'features' => asset('images/rice-features-paddy.jpg'),
        'roles'    => asset('images/rice-roles-terrace.jpg'),
        'contact'  => asset('images/rice-contact-golden.jpg'),
    ];
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
  {{-- The page's photos, as custom properties for resources/css/pages/welcome.css. --}}
  <style>:root { @foreach ($rice as $key => $url)--rice-{{ $key }}: url('{{ $url }}'); @endforeach }</style>
  @vite(['resources/css/pages/welcome.css', 'resources/css/landing.css', 'resources/css/accessibility.css', 'resources/js/landing.js'])
  {{-- Shared iClimate design language (tokens, motion, card interactions),
       loaded after the page's own styles so it refines them. --}}
    @include('partials.global-loader')
</head>
<body>
<x-flash-toast />

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
  <div class="nav-container">
    <a href="{{ url('/') }}" class="nav-logo">
      <img src="{{ asset('images/iclimate-logo-light.png') }}" alt="iClimate" class="brand-logo-img">
    </a>
    <ul class="nav-links" id="navLinks">
      <li><a href="#home" class="nav-link active">Home</a></li>
      <li><a href="#about" class="nav-link">About</a></li>
      <li><a href="#features" class="nav-link">Features</a></li>
      <li><a href="#roles" class="nav-link">User Roles</a></li>
      <li><a href="#contact" class="nav-link">Contact</a></li>
    </ul>
    <div class="nav-actions">
      <a href="{{ route('login') }}" class="nav-login">Log in</a>
      <a href="{{ route('register') }}" class="nav-cta ihb"><span class="ihb-dot" aria-hidden="true"></span><span class="ihb-label">Create account</span><span class="ihb-hover" aria-hidden="true">Create account <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span></a>
      <button class="hamburger" id="hamburger" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero" id="home">
  <div class="hero-inner">
    <div class="hero-text">
      <p class="hero-place"><span data-dia>Lian, Batangas, Philippines</span></p>
      <h1 class="hero-title blur-fade" style="--blur-delay:600ms">Climate-smart rice farming.</h1>
      <p class="hero-desc blur-fade" style="--blur-delay:750ms">Weather, planting advice and rice yield forecasts for Lian, Batangas. All in one place.</p>
      <div class="hero-actions">
        <a href="{{ route('login') }}" class="btn btn-primary btn-lg ihb blur-fade" style="--blur-delay:900ms"><span class="ihb-dot" aria-hidden="true"></span><span class="ihb-label">Get started</span><span class="ihb-hover" aria-hidden="true">Get started <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span></a>
        <a href="#about" class="btn-link blur-fade" style="--blur-delay:1050ms">
          Learn more
          <svg width="8" height="12" viewBox="0 0 8 12" fill="none" aria-hidden="true"><path d="M1.5 1.5 6 6l-4.5 4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </div>
    </div>

    <div class="hero-visual blur-fade" style="--blur-delay:1200ms">
      <p class="hero-map-heading">Current conditions in Lian</p>
      <div class="hero-map-panel">
        <div class="hero-map" id="heroMap" data-lat="14.0381" data-lng="120.6522" data-boundary="{{ asset('geojson/lian-boundary.geojson') }}" aria-label="Map of Lian, Batangas showing the Municipal Agriculture Office"></div>
        <a class="hero-map-link" href="https://www.google.com/maps/search/?api=1&amp;query=14.0381%2C120.6522" target="_blank" rel="noopener noreferrer" aria-label="Open the Municipal Agriculture Office of Lian, Batangas in Google Maps (opens in a new tab)">
          <span class="hero-map-open">Open in Google Maps <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M4.5 2H2v8h8V7.5M7 2h3v3M10 2 5.5 6.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        </a>
        <div class="hero-map-card" id="heroMapCard">
          <div class="hmc-office">Municipal Agriculture Office</div>
          <div class="hmc-location">Poblacion, Lian, Batangas</div>
          <div class="hmc-live" data-hero-live>{{ $heroClimate ? 'Latest saved climate record' : 'Checking live weather…' }}</div>
        </div>
        <div class="hero-weather-strip">
          <div class="hws-item">
            <div class="hws-val" data-hero-temp data-tick>{{ $heroClimate ? round((float) $heroClimate->temperature).'°C' : '--' }}</div>
            <div class="hws-label">Temperature</div>
          </div>
          <div class="hws-item">
            <div class="hws-val" data-hero-humidity data-tick>{{ $heroClimate ? round((float) $heroClimate->humidity).'%' : '--' }}</div>
            <div class="hws-label">Humidity</div>
          </div>
          <div class="hws-item">
            {{-- This wind figure is from a stored historical ClimateRecord, whose wind
                 unit is not established by any repository evidence, so no unit is
                 asserted here (unlike genuinely-km/h live Open-Meteo readings). --}}
            <div class="hws-val" data-hero-wind data-tick>{{ $heroClimate ? round((float) $heroClimate->wind_speed) : '--' }}</div>
            <div class="hws-label">Wind speed</div>
          </div>
          <div class="hws-item">
            <div class="hws-val" data-hero-rain data-tick>{{ $heroClimate ? number_format((float) $heroClimate->rainfall, 1).' mm' : '--' }}</div>
            <div class="hws-label" data-hero-rain-label>{{ $heroClimate ? 'Rainfall (record)' : 'Rainfall today' }}</div>
          </div>
        </div>
      </div>
      <p class="hero-map-credit">Tiles &copy; <a href="https://www.esri.com/" target="_blank" rel="noopener noreferrer">Esri</a> &middot; Sources: Esri, HERE, Garmin, USGS, <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors and the GIS User Community</p>
    </div>
  </div>
</section>

<!-- ABOUT -->
<section class="about-section" id="about">
  <div class="container">
    <div class="about-grid">
      <div class="about-text">
        <div>
          <div class="hero-location fade-up fade-up-1">Lian, Batangas, Philippines
          </div>
          <h2 class="section-title">About iClimate</h2>
          <p class="about-tagline"><span class="tagline-flourish"></span>Understand today. Prepare tomorrow.<span class="tagline-flourish"></span></p>
        </div>
        <p>iClimate is a web-based decision support system that combines climate data and local rice production records to help rice farmers, MAO staff with technicians, and IT personnel make informed, data-driven decisions for better agricultural outcomes in Lian, Batangas.</p>
      </div>

      <div class="about-visual">
        <div class="about-photo">
          <div class="mission-card">
            <div class="mission-icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2c3 4 6 7.5 6 10.5A6 6 0 1 1 4 12.5C4 9.5 7 6 10 2z" stroke="#6F8978" stroke-width="1.6"/></svg>
            </div>
            <div>
              <strong>Our mission</strong>
              <p>To empower the farming community of Lian, Batangas through accurate climate information, smart analytics, and practical recommendations.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="pillars-row">
      <div class="pillar-card pillar-card--wide ic-card-enter" style="--ic-card-delay:0ms">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M2 12c1.5-3.5 4-5 8-5s6.5 1.5 8 5" stroke="#6F8978" stroke-width="1.6" stroke-linecap="round"/><circle cx="10" cy="5.5" r="1.6" fill="#6F8978"/></svg></div>
        <strong>Climate-informed</strong>
        <p>Live weather forecasts, stored climate records and official PAGASA advisories, combined into guidance for Lian's rice fields.</p>
        <ul class="pillar-sources" aria-label="Data sources">
          <li>PAGASA advisories</li>
          <li>Open-Meteo forecasts</li>
          <li>Climate records</li>
        </ul>
      </div>
      <div class="pillar-card ic-card-enter" style="--ic-card-delay:100ms">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2c2.2 3 4 5.3 4 7.5A4 4 0 1 1 6 9.5C6 7.3 7.8 5 10 2z" stroke="#6F8978" stroke-width="1.5"/></svg></div>
        <strong>Rice-focused</strong>
        <p>Specialized in rice production analysis, yield prediction, and planting recommendations.</p>
      </div>
      <div class="pillar-card ic-card-enter" style="--ic-card-delay:200ms">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="3" y="10" width="3" height="7" fill="#6F8978"/><rect x="8.5" y="6" width="3" height="11" fill="#6F8978" opacity="0.85"/><rect x="14" y="3" width="3" height="14" fill="#6F8978" opacity="0.6"/></svg></div>
        <strong>Data-driven</strong>
        <p>Using historical records and analytics to generate accurate insights and reports.</p>
      </div>
      <div class="pillar-card ic-card-enter" style="--ic-card-delay:300ms">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="7" cy="7" r="2.6" stroke="#6F8978" stroke-width="1.4"/><circle cx="14" cy="8" r="2.2" stroke="#6F8978" stroke-width="1.4"/><path d="M2.5 16c0.5-3 2-4.5 4.5-4.5s4 1.5 4.5 4.5" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round"/><path d="M11.5 16c0.4-2.4 1.7-3.7 3.5-3.7s3 1.1 3.5 3" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round"/></svg></div>
        <strong>Community-centered</strong>
        <p>Built for farmers, supported by MAO staff and technicians, and optimized by IT personnel.</p>
      </div>
      <div class="pillar-card ic-card-enter" style="--ic-card-delay:400ms">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2l7 2.5v5c0 4.5-3 7.5-7 8.5-4-1-7-4-7-8.5v-5L10 2z" stroke="#6F8978" stroke-width="1.5" stroke-linejoin="round"/></svg></div>
        <strong>Secure and reliable</strong>
        <p>Ensuring data security, system reliability, and continuous improvement.</p>
      </div>
    </div>

  </div>
</section>

<!-- FEATURES -->
<section class="features-section" id="features">
  <div class="container">
    <div class="section-header">
      <h2>Tools for the rice season</h2>
      <p>Practical information to help you plan, monitor, and decide from planting through harvest.</p>
    </div>

    <div class="features-grid">
      <div class="feature-card ic-card-enter" style="--ic-card-delay:0ms">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M2 13C3.5 9 6.5 7 11 7s7.5 2 9 6" stroke="#8FAF9A" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M5 17c1-2.5 3-4 6-4s5 1.5 6 4" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round"/>
            <circle cx="11" cy="4.5" r="2" fill="#5F8F78"/>
          </svg>
        </div>
        <h3>Weather analysis</h3>
        <p>Access real-time and historical climate data including rainfall, temperature, humidity, wind, and more from PAGASA.</p>
        <span class="fc-badge">Climate records</span>
      </div>
      <div class="feature-card ic-card-enter" style="--ic-card-delay:100ms">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <rect x="2" y="14" width="4" height="6" rx="1" fill="#5F8F78"/>
            <rect x="9" y="9" width="4" height="11" rx="1" fill="#5F8F78" opacity="0.7"/>
            <rect x="16" y="5" width="4" height="15" rx="1" fill="#5F8F78" opacity="0.45"/>
            <path d="M4 10l7-5 7-3" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
        </div>
        <h3>Rice production</h3>
        <p>View and analyze local rice production records and forecasts to support planning and food security.</p>
        <span class="fc-badge">Records</span>
      </div>
      <div class="feature-card ic-card-enter" style="--ic-card-delay:200ms">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <circle cx="11" cy="11" r="8" stroke="#8FAF9A" stroke-width="1.8"/>
            <path d="M11 6v5l3.5 2" stroke="#6F8978" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
        </div>
        <h3>Planting advisory</h3>
        <p>Get data-driven planting recommendations based on season, rainfall outlook, and soil conditions.</p>
        <span class="fc-badge">Advisory</span>
      </div>
      <div class="feature-card ic-card-enter" style="--ic-card-delay:300ms">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M2 11C5 5,8 3,11 3C14 3,17 5,20 11C17 17,14 19,11 19C8 19,5 17,2 11Z" stroke="#8FAF9A" stroke-width="1.8"/>
            <circle cx="11" cy="11" r="3" fill="#5F8F78"/>
          </svg>
        </div>
        <h3>Climate monitoring</h3>
        <p>Monitor long-term climate trends and detect anomalies that affect farming and productivity.</p>
        <span class="fc-badge">Monitoring</span>
      </div>
      <div class="feature-card ic-card-enter" style="--ic-card-delay:400ms">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M11 2c-4 5-7 8.5-7 12.5A7 7 0 1 0 18 14.5C18 10.5 15 7 11 2z" stroke="#8FAF9A" stroke-width="1.8"/>
          </svg>
        </div>
        <h3>Heat map</h3>
        <p>Visualize risk levels of flood, drought, typhoon, and heat across barangays using interactive maps.</p>
        <span class="fc-badge">Risk records</span>
      </div>
      <div class="feature-card ic-card-enter" style="--ic-card-delay:500ms">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M4 3h10l4 4v12H4V3z" stroke="#8FAF9A" stroke-width="1.8"/>
            <path d="M14 3v4h4" stroke="#8FAF9A" stroke-width="1.8"/>
            <path d="M7 10h8M7 13h6M7 16h7" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
        </div>
        <h3>Reports and analytics</h3>
        <p>Generate and download seasonal reports, yield forecasts, and analytics for smarter decision-making.</p>
        <span class="fc-badge">Analytics</span>
      </div>
    </div>

    <div class="how-block">
      <div class="section-header">
        <h2>From records to harvest in four steps</h2>
      </div>
      <div class="how-steps">
        <div class="how-step">
          <div class="how-step-top">
            <div class="how-step-icon">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4.5 13.5A3 3 0 0 1 4 7.6a4 4 0 0 1 7.7-1.2A2.7 2.7 0 0 1 13.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 15V8.5M6.5 11l2.5-2.5L11.5 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <span class="how-step-num">01</span>
          </div>
          <h3>Log climate data</h3>
          <p>We collect real-time and historical climate data from trusted sources like PAGASA.</p>
        </div>
        <div class="how-step">
          <div class="how-step-top">
            <div class="how-step-icon">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2c2.7 2.7 3.6 4.8 3.6 6.75A3.6 3.6 0 1 1 5.4 8.75C5.4 6.8 6.3 4.7 9 2z" stroke="currentColor" stroke-width="1.5"/></svg>
            </div>
            <span class="how-step-num">02</span>
          </div>
          <h3>Get advisories</h3>
          <p>Our system analyzes the data to provide planting and fertilizer recommendations.</p>
        </div>
        <div class="how-step">
          <div class="how-step-top">
            <div class="how-step-icon">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="7.5" cy="7.5" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M11.3 11.3 15 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </div>
            <span class="how-step-num">03</span>
          </div>
          <h3>Monitor risk</h3>
          <p>We track weather risks and climate patterns that may impact your crops.</p>
        </div>
        <div class="how-step">
          <div class="how-step-top">
            <div class="how-step-icon">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3.5 2.5h8l3 3v10h-11v-13z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M6 9h6M6 12h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            </div>
            <span class="how-step-num">04</span>
          </div>
          <h3>Report outcomes</h3>
          <p>Generate reports and yield forecasts to guide better farming decisions.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- USER ROLES -->
<section class="roles-section" id="roles">
  <div class="container">
    <div class="section-header">
      <h2>Who uses iClimate?</h2>
      <p>Information for rice farmers, MAO personnel, and IT support staff, with each role seeing the work it needs.</p>
    </div>
    <div class="roles-grid">

      <!-- Rice Farmer -->
      <div class="role-card ic-card-enter" style="--ic-card-delay:0ms">
        <div class="role-icon-wrap--img" aria-hidden="true">
          <svg width="34" height="34" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="6.5" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M3.5 18c.6-4.8 3-7.5 6.5-7.5s5.9 2.7 6.5 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </div>
        <div class="role-workspace-label">Workspace 1</div>
        <h3>Rice Farmer</h3>
        <p>View climate conditions, receive planting advisories, check weather risk alerts, and access seasonal information through a simple farmer-friendly dashboard.</p>
        <div class="role-divider"></div>
        <div class="role-features-label">Key features</div>
        <ul class="role-features">
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Climate monitoring
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Planting guidance
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Weather risk alerts
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Notifications
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Seasonal reports
          </li>
        </ul>
        <a href="{{ route('login') }}" class="role-cta">
          View dashboard
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="5" height="5" rx="1" fill="currentColor"/><rect x="8" y="1" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="1" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="8" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/></svg>
        </a>
      </div>

      <!-- MAO Personnel -->
      <div class="role-card role-card--highlight ic-card-enter" style="--ic-card-delay:100ms">
        <div class="role-icon-wrap--img" aria-hidden="true">
          <svg width="34" height="34" viewBox="0 0 20 20" fill="none"><path d="M3 8l7-5 7 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 8v9h12V8" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8.5 17v-5h3v5" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
        </div>
        <div class="role-workspace-label">Workspace 2</div>
        <h3>MAO Personnel</h3>
        <p>Manage agricultural records, monitor rice production trends, analyze climate-yield relationships, and generate reports to support farmers and municipal planning.</p>
        <div class="role-divider"></div>
        <div class="role-features-label">Key features</div>
        <ul class="role-features">
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Production monitoring
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Climate analytics
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Report generation
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Record validation
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Heat map monitoring
          </li>
        </ul>
        <a href="{{ route('login') }}" class="role-cta">
          View dashboard
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="5" height="5" rx="1" fill="currentColor"/><rect x="8" y="1" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="1" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="8" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/></svg>
        </a>
      </div>

      <!-- MAO IT Personnel -->
      <div class="role-card ic-card-enter" style="--ic-card-delay:200ms">
        <div class="role-icon-wrap--img" aria-hidden="true">
          <svg width="34" height="34" viewBox="0 0 20 20" fill="none"><rect x="5" y="9" width="10" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M7 9V6a3 3 0 0 1 6 0v3" stroke="currentColor" stroke-width="1.5"/></svg>
        </div>
        <div class="role-workspace-label">Workspace 3</div>
        <h3>IT Personnel</h3>
        <p>Support user access, keep records organized, monitor system activity, and help the MAO team keep iClimate secure and dependable.</p>
        <div class="role-divider"></div>
        <div class="role-features-label">Key features</div>
        <ul class="role-features">
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            User access support
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Activity checks
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Record upkeep
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Account support
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            System reliability
          </li>
        </ul>
        <a href="{{ route('login') }}" class="role-cta">
          View dashboard
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="5" height="5" rx="1" fill="currentColor"/><rect x="8" y="1" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="1" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="8" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/></svg>
        </a>
      </div>

    </div>

  </div>
</section>

<!-- CONTACT -->
<section class="contact-section" id="contact">
  <div class="container">
    <div class="section-header">
      <h2>Have questions about iClimate?</h2>
      <p>Reach the team at the Municipal Agriculture Office of Lian, Batangas. We support farmers, MAO personnel, and IT experts using the platform.</p>
    </div>
    <div class="contact-grid">
      <div class="contact-info">
        <div class="contact-details">
          <div class="cd-item">
            <div class="cd-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M9 16s5.5-5.1 5.5-9A5.5 5.5 0 0 0 3.5 7c0 3.9 5.5 9 5.5 9Z" stroke="#6F8978" stroke-width="1.4" stroke-linejoin="round"/><circle cx="9" cy="7" r="2" stroke="#6F8978" stroke-width="1.4"/></svg>
            </div>
            <div class="cd-text">
              <strong>Address</strong>
              <span>Municipal Agriculture Office, Lian, Batangas, Philippines</span>
              <a class="cd-link" href="https://www.google.com/maps/dir/?api=1&amp;destination=14.0381,120.6522" target="_blank" rel="noopener noreferrer">Get directions</a>
            </div>
          </div>
          <div class="cd-item">
            <div class="cd-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="2" y="4" width="14" height="10" rx="1.5" stroke="#6F8978" stroke-width="1.4"/><path d="M2.5 5l6.5 5 6.5-5" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="cd-text">
              <strong>Email</strong>
              <a class="cd-value" href="mailto:mao@lianbatangas.gov.ph">mao@lianbatangas.gov.ph</a>
            </div>
          </div>
          <div class="cd-item">
            <div class="cd-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M3.5 2.5h3l1.5 4-2 1.3a9 9 0 0 0 4.2 4.2l1.3-2 4 1.5v3a1.5 1.5 0 0 1-1.6 1.5A13.5 13.5 0 0 1 2 4.1 1.5 1.5 0 0 1 3.5 2.5Z" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="cd-text">
              <strong>Phone</strong>
              <a class="cd-value" href="tel:+639171234567">+63 917 123 4567</a>
            </div>
          </div>
          <div class="cd-item">
            <div class="cd-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="6.5" stroke="#6F8978" stroke-width="1.4"/><path d="M9 5.5V9l3 1.5" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="cd-text">
              <strong>Office hours</strong>
              <div class="cd-hours">
                <div class="cd-hours-row"><span>Monday to Friday</span><span>8:00 AM to 5:00 PM</span></div>
                <div class="cd-hours-row"><span>Saturday</span><span>By appointment</span></div>
                <div class="cd-hours-row"><span>Sunday</span><span>Closed</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <form class="contact-form-card" id="contactForm" method="POST" action="{{ route('contact.send') }}" novalidate>
        @csrf
        <div class="cfc-header">
          <div class="cfc-icon">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="2" y="4" width="14" height="10" rx="1.5" stroke="#6F8978" stroke-width="1.4"/><path d="M2.5 5l6.5 5 6.5-5" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <div>
            <h3>Send us a message</h3>
            <p class="contact-form-sub">We typically respond within one business day.</p>
          </div>
        </div>
        <div class="form-group">
          <label for="contactName">Full name</label>
          <input type="text" class="form-input" id="contactName" name="name" maxlength="120" autocomplete="name" required placeholder="Juan Dela Cruz"/>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="contactEmail">Email address</label>
            <input type="email" class="form-input" id="contactEmail" name="email" maxlength="255" autocomplete="email" required placeholder="juan@gmail.com"/>
          </div>
          <div class="form-group">
            <label for="contactRole">Role</label>
            <select class="form-input" id="contactRole" name="role">
              <option value="" selected disabled>Select your role</option>
              <option>Rice farmer</option>
              <option>MAO personnel</option>
              <option>IT personnel</option>
              <option>Other</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label for="contactMessage">Message</label>
          <textarea class="form-input" id="contactMessage" name="message" rows="4" maxlength="2000" required placeholder="How can we help you?"></textarea>
        </div>
        {{-- Left empty by people; automated form fillers complete it and are refused. --}}
        <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">
        <p id="contactStatus" role="status" aria-live="polite" hidden style="margin:0 0 12px;font-size:.9rem;font-weight:600;"></p>
        <button type="submit" class="btn btn-dark btn-full" id="contactSubmit">
          Send message
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </form>

    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="container">
    <div class="ft-top">
      <div class="ft-brand">
        <a href="{{ url('/') }}" class="ft-logo" aria-label="iClimate home">
          <img src="{{ asset('images/iclimate-logo-light.png') }}" alt="iClimate">
        </a>
        <p class="ft-line">Weather, planting advice and rice yield forecasts for the farmers of Lian, Batangas.</p>
        <div class="ft-social">
          <a href="https://www.facebook.com/profile.php?id=61551743430540" class="footer-social-icon" target="_blank" rel="noopener noreferrer" aria-label="iClimate on Facebook (opens in a new tab)" title="Facebook"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-7.5h2.5l.4-3H13.5V8.5c0-.9.2-1.5 1.5-1.5h1.6V4.3C16.3 4.2 15.3 4 14.1 4c-2.4 0-4.1 1.5-4.1 4.2V10.5H7.5v3H10V21h3.5z"/></svg></a>
          <div class="footer-email" data-email-menu data-email="mao_lian@yahoo.com">
            <a href="mailto:mao_lian@yahoo.com" class="footer-social-icon" aria-label="Email iClimate at mao_lian@yahoo.com" title="Email us: mao_lian@yahoo.com" aria-haspopup="true" aria-expanded="false" data-email-trigger>
            <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="2" y="4" width="14" height="10" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M2.5 5l6.5 5 6.5-5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
            <div class="footer-email-menu" role="menu" hidden data-email-panel>
              <div class="footer-email-addr">mao_lian@yahoo.com</div>
              <a role="menuitem" target="_blank" rel="noopener noreferrer" href="https://mail.google.com/mail/?view=cm&amp;fs=1&amp;to=mao_lian@yahoo.com">Write in Gmail</a>
              <a role="menuitem" target="_blank" rel="noopener noreferrer" href="https://compose.mail.yahoo.com/?to=mao_lian@yahoo.com">Write in Yahoo Mail</a>
              <a role="menuitem" href="mailto:mao_lian@yahoo.com">Use my email app</a>
              <button type="button" role="menuitem" data-email-copy>Copy address</button>
            </div>
          </div>
        </div>
      </div>

      <nav class="ft-cols" aria-label="Footer">
        <div class="ft-col">
          <h2 class="ft-head">Explore</h2>
          <ul>
            <li><a href="#about">About</a></li>
            <li><a href="#features">Features</a></li>
            <li><a href="#roles">User roles</a></li>
            <li><a href="#contact">Contact</a></li>
          </ul>
        </div>
        <div class="ft-col">
          <h2 class="ft-head">Account</h2>
          <ul>
            <li><a href="{{ route('login') }}">Log in</a></li>
            <li><a href="{{ route('register') }}">Create account</a></li>
            <li><a href="{{ route('password.request') }}">Forgot password</a></li>
          </ul>
        </div>
        <div class="ft-col">
          <h2 class="ft-head">Visit us</h2>
          <ul class="ft-plain">
            <li>Municipal Agriculture Office</li>
            <li>Lian, Batangas, Philippines</li>
            <li>Mon to Fri, 8 AM to 5 PM</li>
            <li><a href="https://www.google.com/maps/dir/?api=1&amp;destination=14.0381,120.6522" target="_blank" rel="noopener noreferrer">Get directions</a></li>
          </ul>
        </div>
      </nav>
    </div>

    <div class="ft-bottom">
      <p>&copy; 2026 iClimate Research Group &middot; Batangas State University ARASOF-Nasugbu</p>
      <a href="#home" class="ft-top-link">
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
        panel.style.setProperty('--arrow-x', `${r.left + r.width / 2 - left}px`);
        panel.classList.toggle('opens-up', up);
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
        copyBtn.classList.add('is-copied');
        setTimeout(() => { copyBtn.textContent = 'Copy address'; copyBtn.classList.remove('is-copied'); setOpen(false); }, 1200);
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
      if (!el || value === null || value === undefined) return;
      el.textContent = value;
      if (el.hasAttribute('data-tick') && window.icTick) window.icTick(el);
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
      set('[data-hero-live]', `Live now · ${w.condition_label || 'Current weather'}${w.updated_label ? ' · ' + w.updated_label : ''}`);
    } catch (e) { /* keep the saved record */ }
  });

  document.addEventListener('DOMContentLoaded', () => {
    const navLinks = document.getElementById('navLinks');
    const hamburger = document.getElementById('hamburger');
    const links = document.querySelectorAll('.nav-link');

    hamburger?.addEventListener('click', () => navLinks?.classList.toggle('open'));
    // Highlight the clicked link straight away. While the page scrolls to it, the scroll spy below
    // stays quiet so the sections passed on the way are not highlighted one after another.
    let spyPaused = false;
    let resumeTimer;
    const resumeSpy = () => { spyPaused = false; clearTimeout(resumeTimer); };
    window.addEventListener('scrollend', resumeSpy);
    links.forEach(link => link.addEventListener('click', () => {
      navLinks?.classList.remove('open');
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

    // Staggered card entrance (pillar/feature/role cards): reveal each
    // .ic-card-enter once it scrolls into view, then stop watching it —
    // no looping. Reduced-motion users (or browsers without
    // IntersectionObserver) get the cards shown immediately, unanimated.
    // Section headings and text blocks reveal the same way as the cards;
    // grouped siblings get a small stagger.
    [['.section-header', 0], ['.about-text', 0], ['.about-visual', 120], ['.contact-info', 0], ['.contact-form-card, .contact-form', 120]]
      .forEach(([selector, delay]) => {
        document.querySelectorAll(selector).forEach((block) => {
          if (block.classList.contains('ic-card-enter')) return;
          block.classList.add('ic-card-enter');
          block.style.setProperty('--ic-card-delay', delay + 'ms');
        });
      });

    // "Send us a message": delivered to the MAO staff as a notification, without leaving the page.
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
      const status = document.getElementById('contactStatus');
      const submit = document.getElementById('contactSubmit');
      const say = (text, ok) => { status.textContent = text; status.style.color = ok ? '#1F6B45' : '#B42318'; status.hidden = false; };
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

    const enterCards = document.querySelectorAll('.ic-card-enter');
    if (enterCards.length) {
      const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (reduceMotion || !('IntersectionObserver' in window)) {
        enterCards.forEach(card => card.classList.add('ic-card-enter-visible'));
      } else {
        const cardObserver = new IntersectionObserver((entries) => {
          entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('ic-card-enter-visible');
            cardObserver.unobserve(entry.target);
            // Once revealed, drop the delay so later hover transitions are instant.
            setTimeout(() => entry.target.style.setProperty('--ic-card-delay', '0ms'), 1200);
          });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
        enterCards.forEach(card => cardObserver.observe(card));
      }
    }
  });

</script>
</body>
</html>
