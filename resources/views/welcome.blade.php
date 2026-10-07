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
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  {{-- The page's photos, as custom properties for resources/css/pages/welcome.css. --}}
  <style>:root { @foreach ($rice as $key => $url)--rice-{{ $key }}: url('{{ $url }}'); @endforeach }</style>
  @vite('resources/css/pages/welcome.css')
  {{-- Shared iClimate design language (tokens, motion, card interactions),
       loaded after the page's own styles so it refines them. --}}
  @vite(['resources/css/landing.css', 'resources/js/landing.js'])
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
      <li><a href="#roles" class="nav-link">User Roles</a></li>
      <li><a href="#features" class="nav-link">Features</a></li>
      <li><a href="#contact" class="nav-link">Contact</a></li>
    </ul>
    <div class="nav-actions">
      <a href="{{ route('login') }}" class="nav-login">Login</a>
      <a href="{{ route('register') }}" class="nav-cta">Create Account</a>
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
      <div class="hero-location fade-up fade-up-1">
        <svg width="10" height="12" viewBox="0 0 10 12" fill="none">
          <path d="M5 0C2.24 0 0 2.24 0 5c0 3.75 5 7 5 7s5-3.25 5-7c0-2.76-2.24-5-5-5zm0 6.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" fill="currentColor"/>
        </svg>
        Lian, Batangas &mdash; Philippines
      </div>
      <h1 class="hero-title fade-up fade-up-2">
        Climate-smart<br>
        <span class="hero-title-italic">rice farming.</span>
      </h1>
      <p class="hero-subtitle fade-up fade-up-2">Weather and Farming Guide for Lian, Batangas</p>
      <p class="hero-desc fade-up fade-up-3">A web-based weather impact analysis and rice yield prediction platform for Lian, Batangas &mdash; built for rice farmers, MAO staff with technicians, and IT personnel.</p>
      <div class="hero-actions fade-up fade-up-3">
        <a href="{{ route('login') }}" class="btn btn-primary btn-lg">
          <svg width="15" height="15" viewBox="0 0 20 20" fill="none"><path d="M10 2c3 4 6 7.5 6 10.5A6 6 0 1 1 4 12.5C4 9.5 7 6 10 2z" stroke="#163B2D" stroke-width="1.6"/></svg>
          Get Started
        </a>
        <a href="#about" class="btn-ghost-light btn">
          Learn More
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </div>
      <div class="trust-grid fade-up fade-up-4">
        <div class="trust-item">
          <div class="trust-icon">
            <svg width="18" height="18" viewBox="0 0 22 22" fill="none"><path d="M11 2a5 5 0 100 10 5 5 0 000-10zM3 20c0-4.4 3.6-7 8-7s8 2.6 8 7" stroke="#8FAF9A" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <div>
            <div class="trust-num">3</div>
            <div class="trust-label">User Roles</div>
          </div>
        </div>
        <div class="trust-item">
          <div class="trust-icon">
            <svg width="18" height="18" viewBox="0 0 22 22" fill="none"><path d="M3 17V9M9 17V5M15 17v-7M19 17V3" stroke="#8FAF9A" stroke-width="1.6" stroke-linecap="round"/></svg>
          </div>
          <div>
            <div class="trust-num">24/7</div>
            <div class="trust-label">Real-Time Monitoring</div>
          </div>
        </div>
        <div class="trust-item">
          <div class="trust-icon">
            <svg width="18" height="18" viewBox="0 0 22 22" fill="none"><path d="M6.5 15a4 4 0 01-.5-7.97A5.5 5.5 0 0116.9 8.6 3.6 3.6 0 0116 15.5H6.5z" stroke="#8FAF9A" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 18.5v1M11.5 18.5v2M15 18.5v1" stroke="#8FAF9A" stroke-width="1.6" stroke-linecap="round"/></svg>
          </div>
          <div>
            <div class="trust-num">PAGASA</div>
            <div class="trust-label">Verified Data Source</div>
          </div>
        </div>
      </div>
    </div>

    <div class="hero-visual fade-up fade-up-3">
      <div class="hero-map-panel">
        <div class="hero-map-dot"></div>
        <div class="hero-map-card">
          <div class="hmc-location">Lian, Batangas</div>
          <div class="hmc-office">Municipal Agricultural Office</div>
          <div class="hmc-live" data-hero-live>{{ $heroClimate ? 'Latest saved climate record' : 'Checking live weather…' }}</div>
        </div>
        <div class="hero-weather-strip">
          <div class="hws-item">
            <svg class="hws-icon" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M5.5 10.5a3 3 0 0 1 .6-5.9 4 4 0 0 1 7.7.7A2.7 2.7 0 0 1 13.5 10.5h-8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6 13.5v1M9 13.5v1.6M12 13.5v1" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            <div><div class="hws-val" data-hero-temp>{{ $heroClimate ? round((float) $heroClimate->temperature).'°C' : '--' }}</div><div class="hws-label">Temperature</div></div>
          </div>
          <div class="hws-item">
            <svg class="hws-icon" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2c1.6 2.2 3 4.3 3 6.1A3 3 0 1 1 7 8.1C7 6.3 8.4 4.2 10 2Z" stroke="currentColor" stroke-width="1.4"/></svg>
            <div><div class="hws-val" data-hero-humidity>{{ $heroClimate ? round((float) $heroClimate->humidity).'%' : '--' }}</div><div class="hws-label">Humidity</div></div>
          </div>
          <div class="hws-item">
            <svg class="hws-icon" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M2 7h9a2.2 2.2 0 1 0-2.2-2.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M2 11.5h12.2a2.4 2.4 0 1 1-2.4 2.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            {{-- This wind figure is from a stored historical ClimateRecord, whose wind
                 unit is not established by any repository evidence, so no unit is
                 asserted here (unlike genuinely-km/h live Open-Meteo readings). --}}
            <div><div class="hws-val" data-hero-wind>{{ $heroClimate ? round((float) $heroClimate->wind_speed) : '--' }}</div><div class="hws-label">Wind Speed</div></div>
          </div>
          <div class="hws-item">
            <svg class="hws-icon" width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="4" y="3" width="2.6" height="11" rx="1" fill="currentColor"/><rect x="8.7" y="6" width="2.6" height="8" rx="1" fill="currentColor" opacity=".7"/><rect x="13.4" y="8.5" width="2.6" height="5.5" rx="1" fill="currentColor" opacity=".5"/></svg>
            <div><div class="hws-val" data-hero-rain>{{ $heroClimate ? number_format((float) $heroClimate->rainfall, 1).' mm' : '--' }}</div><div class="hws-label" data-hero-rain-label>{{ $heroClimate ? 'Rainfall (record)' : 'Rainfall Today' }}</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ABOUT -->
<section class="about-section" id="about">
  <div class="container">
    <div class="about-grid">
      <div class="about-text">
        <div>
          <div class="hero-location fade-up fade-up-1">
            <svg width="10" height="12" viewBox="0 0 10 12" fill="none">
              <path d="M5 0C2.24 0 0 2.24 0 5c0 3.75 5 7 5 7s5-3.25 5-7c0-2.76-2.24-5-5-5zm0 6.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" fill="currentColor"/>
            </svg>
            Lian, Batangas &mdash; Philippines
          </div>
          <h2 class="section-title">About <em>iClimate</em></h2>
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
              <strong>Our Mission</strong>
              <p>To empower the farming community of Lian, Batangas through accurate climate information, smart analytics, and practical recommendations.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="pillars-row">
      <div class="pillar-card ic-card-enter" style="--ic-card-delay:0ms">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M2 12c1.5-3.5 4-5 8-5s6.5 1.5 8 5" stroke="#6F8978" stroke-width="1.6" stroke-linecap="round"/><circle cx="10" cy="5.5" r="1.6" fill="#6F8978"/></svg></div>
        <strong>Climate-Informed</strong>
        <p>Integrating PAGASA climate data for real-time weather monitoring and seasonal forecasting.</p>
        <p>Combining stored climate records, live weather forecasts, and official PAGASA advisory references for localized guidance.</p>
      </div>
      <div class="pillar-card ic-card-enter" style="--ic-card-delay:100ms">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2c2.2 3 4 5.3 4 7.5A4 4 0 1 1 6 9.5C6 7.3 7.8 5 10 2z" stroke="#6F8978" stroke-width="1.5"/></svg></div>
        <strong>Rice-Focused</strong>
        <p>Specialized in rice production analysis, yield prediction, and planting recommendations.</p>
      </div>
      <div class="pillar-card ic-card-enter" style="--ic-card-delay:200ms">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="3" y="10" width="3" height="7" fill="#6F8978"/><rect x="8.5" y="6" width="3" height="11" fill="#6F8978" opacity="0.85"/><rect x="14" y="3" width="3" height="14" fill="#6F8978" opacity="0.6"/></svg></div>
        <strong>Data-Driven</strong>
        <p>Using historical records and analytics to generate accurate insights and reports.</p>
      </div>
      <div class="pillar-card ic-card-enter" style="--ic-card-delay:300ms">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="7" cy="7" r="2.6" stroke="#6F8978" stroke-width="1.4"/><circle cx="14" cy="8" r="2.2" stroke="#6F8978" stroke-width="1.4"/><path d="M2.5 16c0.5-3 2-4.5 4.5-4.5s4 1.5 4.5 4.5" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round"/><path d="M11.5 16c0.4-2.4 1.7-3.7 3.5-3.7s3 1.1 3.5 3" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round"/></svg></div>
        <strong>Community-Centered</strong>
        <p>Built for farmers, supported by MAO staff and technicians, and optimized by IT personnel.</p>
      </div>
      <div class="pillar-card ic-card-enter" style="--ic-card-delay:400ms">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2l7 2.5v5c0 4.5-3 7.5-7 8.5-4-1-7-4-7-8.5v-5L10 2z" stroke="#6F8978" stroke-width="1.5" stroke-linejoin="round"/></svg></div>
        <strong>Secure &amp; Reliable</strong>
        <p>Ensuring data security, system reliability, and continuous improvement.</p>
      </div>
    </div>

    <div class="who-we-are">
      <div class="who-we-are-text">
        <span class="eyebrow">Who We Are</span>
        <h2>Built for Lian, Batangas</h2>
        <p>iClimate is developed to address the unique agricultural challenges of Lian, Batangas. By combining local knowledge with modern technology, we help our community adapt to climate variability and build a more resilient future.</p>
      </div>
      <div class="wwa-grid">
        <div class="wwa-stat ic-card-enter" style="--ic-card-delay:0ms">
          <div class="wwa-icon"><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="6.5" cy="6" r="2.2" stroke="#8FAF9A" stroke-width="1.3"/><circle cx="12" cy="7" r="1.8" stroke="#8FAF9A" stroke-width="1.3"/><path d="M2 15c.4-2.7 1.8-4 4.5-4s4 1.3 4.5 4" stroke="#8FAF9A" stroke-width="1.3" stroke-linecap="round"/><path d="M10.5 15c.3-2 1.5-3.2 3-3.2s2.7.9 3 2.9" stroke="#8FAF9A" stroke-width="1.3" stroke-linecap="round"/></svg></div>
          <div class="wwa-num">3</div>
          <div class="wwa-label">User Roles</div>
          <p class="wwa-desc">Rice Farmers, MAO Staff with Technician, and IT Personnel</p>
        </div>
        <div class="wwa-stat ic-card-enter" style="--ic-card-delay:100ms">
          <div class="wwa-icon"><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="3" y="10" width="2.6" height="5" fill="#8FAF9A"/><rect x="7.7" y="6" width="2.6" height="9" fill="#8FAF9A" opacity="0.85"/><rect x="12.4" y="3" width="2.6" height="12" fill="#8FAF9A" opacity="0.6"/></svg></div>
          <div class="wwa-num">24/7</div>
          <div class="wwa-label">Real-Time Monitoring</div>
          <p class="wwa-desc">Continuous weather and climate updates</p>
        </div>
        <div class="wwa-stat ic-card-enter" style="--ic-card-delay:200ms">
          <div class="wwa-icon"><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2l6 2v4c0 4-2.6 6.7-6 7.5-3.4-.8-6-3.5-6-7.5V4l6-2z" stroke="#8FAF9A" stroke-width="1.4" stroke-linejoin="round"/></svg></div>
          <div class="wwa-num">PAGASA</div>
          <div class="wwa-label">Verified Data Source</div>
          <p class="wwa-desc">Trusted climate data from PAGASA</p>
        </div>
        <div class="wwa-stat ic-card-enter" style="--ic-card-delay:300ms">
          <div class="wwa-icon"><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2a5 5 0 0 1 5 5c0 3.5-5 9-5 9s-5-5.5-5-9a5 5 0 0 1 5-5z" stroke="#8FAF9A" stroke-width="1.4"/><circle cx="9" cy="7" r="1.6" stroke="#8FAF9A" stroke-width="1.2"/></svg></div>
          <div class="wwa-num">1 Goal</div>
          <div class="wwa-label">Stronger Farming Community</div>
          <p class="wwa-desc">Better decisions for better harvests</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="features-section" id="features">
  <div class="container">
    <div class="section-header">
      <span class="eyebrow">Features</span>
      <h2>Six tools, one <em>growing</em> cycle</h2>
      <p>Each module helps you plan, monitor, and decide with confidence &mdash; from planting to harvest.</p>
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
        <h3>Weather Analysis</h3>
        <p>Access real-time and historical climate data including rainfall, temperature, humidity, wind, and more from PAGASA.</p>
        <span class="fc-badge">Climate Records</span>
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
        <h3>Rice Production</h3>
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
        <h3>Planting Advisory</h3>
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
        <h3>Climate Monitoring</h3>
        <p>Monitor long-term climate trends and detect anomalies that affect farming and productivity.</p>
        <span class="fc-badge">Monitoring</span>
      </div>
      <div class="feature-card ic-card-enter" style="--ic-card-delay:400ms">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M11 2c-4 5-7 8.5-7 12.5A7 7 0 1 0 18 14.5C18 10.5 15 7 11 2z" stroke="#8FAF9A" stroke-width="1.8"/>
          </svg>
        </div>
        <h3>Heat Map</h3>
        <p>Visualize risk levels of flood, drought, typhoon, and heat across barangays using interactive maps.</p>
        <span class="fc-badge">Risk Records</span>
      </div>
      <div class="feature-card ic-card-enter" style="--ic-card-delay:500ms">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M4 3h10l4 4v12H4V3z" stroke="#8FAF9A" stroke-width="1.8"/>
            <path d="M14 3v4h4" stroke="#8FAF9A" stroke-width="1.8"/>
            <path d="M7 10h8M7 13h6M7 16h7" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
        </div>
        <h3>Reports &amp; Analytics</h3>
        <p>Generate and download seasonal reports, yield forecasts, and analytics for smarter decision-making.</p>
        <span class="fc-badge">Analytics</span>
      </div>
    </div>

    <div class="how-block">
      <div class="section-header">
        <span class="eyebrow">How It Works</span>
        <h2>From records to<br>harvest, in <em>four steps</em></h2>
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
      <span class="eyebrow">User Roles</span>
      <h2>Who Uses <em>iClimate?</em></h2>
      <p>iClimate supports three role-based workspaces for Rice Farmers, MAO Personnel, and IT Experts, each designed to support climate-informed agricultural planning and decision-making.</p>
    </div>
    <div class="roles-grid">

      <!-- Rice Farmer -->
      <div class="role-card ic-card-enter" style="--ic-card-delay:0ms">
        <div class="role-icon-wrap--img" aria-hidden="true">
          <svg width="34" height="34" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="6.5" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M3.5 18c.6-4.8 3-7.5 6.5-7.5s5.9 2.7 6.5 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </div>
        <div class="role-workspace-label">Workspace 01</div>
        <h3>Rice Farmer</h3>
        <p>View climate conditions, receive planting advisories, check weather risk alerts, and access seasonal information through a simple farmer-friendly dashboard.</p>
        <div class="role-divider"></div>
        <div class="role-features-label">Key Features</div>
        <ul class="role-features">
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Climate Monitoring
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Planting Guidance
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Weather Risk Alerts
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Notifications
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Seasonal Reports
          </li>
        </ul>
        <a href="{{ route('login') }}" class="role-cta">
          View Dashboard
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="5" height="5" rx="1" fill="currentColor"/><rect x="8" y="1" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="1" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="8" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/></svg>
        </a>
      </div>

      <!-- MAO Personnel -->
      <div class="role-card role-card--highlight ic-card-enter" style="--ic-card-delay:100ms">
        <div class="role-icon-wrap--img" aria-hidden="true">
          <svg width="34" height="34" viewBox="0 0 20 20" fill="none"><path d="M3 8l7-5 7 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 8v9h12V8" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8.5 17v-5h3v5" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
        </div>
        <div class="role-workspace-label">Workspace 02</div>
        <h3>MAO Personnel</h3>
        <p>Manage agricultural records, monitor rice production trends, analyze climate-yield relationships, and generate reports to support farmers and municipal planning.</p>
        <div class="role-divider"></div>
        <div class="role-features-label">Key Features</div>
        <ul class="role-features">
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Production Monitoring
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Climate Analytics
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Report Generation
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Record Validation
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Heat Map Monitoring
          </li>
        </ul>
        <a href="{{ route('login') }}" class="role-cta">
          View Dashboard
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="5" height="5" rx="1" fill="currentColor"/><rect x="8" y="1" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="1" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="8" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/></svg>
        </a>
      </div>

      <!-- IT Expert -->
      <div class="role-card ic-card-enter" style="--ic-card-delay:200ms">
        <div class="role-icon-wrap--img" aria-hidden="true">
          <svg width="34" height="34" viewBox="0 0 20 20" fill="none"><rect x="5" y="9" width="10" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M7 9V6a3 3 0 0 1 6 0v3" stroke="currentColor" stroke-width="1.5"/></svg>
        </div>
        <div class="role-workspace-label">Workspace 03</div>
        <h3>IT Expert</h3>
        <p>Manage user accounts, monitor system activity, review logs, maintain records, and help ensure that iClimate remains secure, organized, and reliable.</p>
        <div class="role-divider"></div>
        <div class="role-features-label">Key Features</div>
        <ul class="role-features">
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            User Management
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            System Logs
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Record Maintenance
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Access Control
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Security Monitoring
          </li>
        </ul>
        <a href="{{ route('login') }}" class="role-cta">
          View Dashboard
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="5" height="5" rx="1" fill="currentColor"/><rect x="8" y="1" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="1" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="8" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/></svg>
        </a>
      </div>

    </div>

    <div class="roles-connect">
      <div class="connect-bar">
        <div class="connect-intro">
          <div class="connect-icon connect-icon-lg">
            <svg width="24" height="24" viewBox="0 0 22 22" fill="none"><path d="M11 20c-5-1-8-5-8-11 6 0 10 3 11 8 1-5 5-8 11-8 0 6-3 10-8 11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <h3>One climate picture, three vantage points</h3>
        </div>
        <div class="connect-divider"></div>
        <div class="connect-item">
          <div class="connect-icon">
            <svg width="18" height="18" viewBox="0 0 22 22" fill="none"><path d="M11 20c-5-1-8-5-8-11 6 0 10 3 11 8 1-5 5-8 11-8 0 6-3 10-8 11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <div>
            <h4>Rice Farmer</h4>
            <p>Follows planting advisories and weather alerts in the field.</p>
          </div>
        </div>
        <div class="connect-divider"></div>
        <div class="connect-item">
          <div class="connect-icon">
            <svg width="18" height="18" viewBox="0 0 22 22" fill="none"><circle cx="7.5" cy="6" r="3" fill="currentColor"/><path d="M2.5 17c.5-4.5 2.7-7 5-7s4.5 2.5 5 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="15.5" cy="7.5" r="2.7" fill="currentColor" opacity="0.7"/><path d="M11.5 18c.4-3.5 2.3-5.5 4-5.5s3.6 2 4 5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.7"/></svg>
          </div>
          <div>
            <h4>MAO Personnel</h4>
            <p>Validates records and turns data into reports for planning.</p>
          </div>
        </div>
        <div class="connect-divider"></div>
        <div class="connect-item">
          <div class="connect-icon">
            <svg width="18" height="18" viewBox="0 0 22 22" fill="none"><path d="M6 5l-4 6 4 6M16 5l4 6-4 6M13 3L9 19" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <div>
            <h4>IT Expert</h4>
            <p>Keeps accounts, access, and system records secure for everyone.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CONTACT -->
<section class="contact-section" id="contact">
  <div class="contact-leaf-float contact-leaf-1" aria-hidden="true"></div>
  <div class="contact-leaf-float contact-leaf-2" aria-hidden="true"></div>
  <div class="contact-leaf-float contact-leaf-3" aria-hidden="true"></div>
  <div class="contact-leaf-float contact-leaf-4" aria-hidden="true"></div>
  <div class="contact-leaf-float contact-leaf-5" aria-hidden="true"></div>
  <div class="container">
    <div class="contact-grid">
      <div class="contact-info">
        <div class="hero-location">
          <svg width="10" height="12" viewBox="0 0 10 12" fill="none">
            <path d="M5 0C2.24 0 0 2.24 0 5c0 3.75 5 7 5 7s5-3.25 5-7c0-2.76-2.24-5-5-5zm0 6.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" fill="currentColor"/>
          </svg>
          Lian, Batangas &mdash; Philippines
        </div>
        <h2>Have questions about <em>iClimate?</em></h2>
        <p class="about-tagline"><span class="tagline-flourish"></span>We&rsquo;re here to help.<span class="tagline-flourish"></span></p>
        <p>Reach out to the team at the Municipal Agricultural Office of Lian, Batangas. We support farmers, MAO personnel, and IT experts using the platform.</p>
        <div class="contact-details">
          <div class="cd-item">
            <div class="cd-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M9 16s5.5-5.1 5.5-9A5.5 5.5 0 0 0 3.5 7c0 3.9 5.5 9 5.5 9Z" stroke="#6F8978" stroke-width="1.4" stroke-linejoin="round"/><circle cx="9" cy="7" r="2" stroke="#6F8978" stroke-width="1.4"/></svg>
            </div>
            <div class="cd-text">
              <strong>Address</strong>
              <span>Municipal Agricultural Office, Lian, Batangas, Philippines</span>
            </div>
          </div>
          <div class="cd-item">
            <div class="cd-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="2" y="4" width="14" height="10" rx="1.5" stroke="#6F8978" stroke-width="1.4"/><path d="M2.5 5l6.5 5 6.5-5" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="cd-text">
              <strong>Email</strong>
              <span>mao@lianbatangas.gov.ph</span>
            </div>
          </div>
          <div class="cd-item">
            <div class="cd-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M3.5 2.5h3l1.5 4-2 1.3a9 9 0 0 0 4.2 4.2l1.3-2 4 1.5v3a1.5 1.5 0 0 1-1.6 1.5A13.5 13.5 0 0 1 2 4.1 1.5 1.5 0 0 1 3.5 2.5Z" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="cd-text">
              <strong>Phone</strong>
              <span>+63 917 123 4567</span>
            </div>
          </div>
          <div class="cd-item">
            <div class="cd-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="6.5" stroke="#6F8978" stroke-width="1.4"/><path d="M9 5.5V9l3 1.5" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="cd-text">
              <strong>Office Hours</strong>
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
          <label for="contactName">Full Name</label>
          <input type="text" class="form-input" id="contactName" name="name" maxlength="120" autocomplete="name" required placeholder="Juan Dela Cruz"/>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="contactEmail">Email Address</label>
            <input type="email" class="form-input" id="contactEmail" name="email" maxlength="255" autocomplete="email" required placeholder="juan@gmail.com"/>
          </div>
          <div class="form-group">
            <label for="contactRole">Role</label>
            <select class="form-input" id="contactRole" name="role">
              <option value="" selected disabled>Select your role</option>
              <option>Rice Farmer</option>
              <option>MAO Personnel</option>
              <option>IT Personnel</option>
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
          Send Message
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </form>

      <div class="contact-methods">
        <div class="contact-method ic-card-enter" style="--ic-card-delay:0ms">
          <div class="contact-method-icon">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3.5 2.5h3l1.5 4-2 1.3a9 9 0 0 0 4.2 4.2l1.3-2 4 1.5v3a1.5 1.5 0 0 1-1.6 1.5A13.5 13.5 0 0 1 2 4.1 1.5 1.5 0 0 1 3.5 2.5Z" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <h3>Call Us</h3>
          <p>Speak with the MAO front desk about records, advisories, or general inquiries.</p>
          <a href="tel:+639171234567" class="contact-method-link">
            Call During Office Hours
            <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        </div>
        <div class="contact-method ic-card-enter" style="--ic-card-delay:100ms">
          <div class="contact-method-icon">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="2" y="4" width="14" height="10" rx="1.5" stroke="#6F8978" stroke-width="1.4"/><path d="M2.5 5l6.5 5 6.5-5" stroke="#6F8978" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <h3>Email Us</h3>
          <p>Email the team directly for account setup, data corrections, or partnership questions.</p>
          <a href="mailto:mao@lianbatangas.gov.ph" class="contact-method-link">
            Send An Email
            <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        </div>
        <div class="contact-method ic-card-enter" style="--ic-card-delay:200ms">
          <div class="contact-method-icon">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 16s5.5-5.1 5.5-9A5.5 5.5 0 0 0 3.5 7c0 3.9 5.5 9 5.5 9Z" stroke="#6F8978" stroke-width="1.4" stroke-linejoin="round"/><circle cx="9" cy="7" r="2" stroke="#6F8978" stroke-width="1.4"/></svg>
          </div>
          <h3>Visit Us</h3>
          <p>Visit the Municipal Agricultural Office to consult on-site during standard business hours.</p>
          <a href="#contact" class="contact-method-link">
            Get Directions
            <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <!-- Column 1: Brand -->
      <div class="footer-brand">
        <a href="{{ url('/') }}" class="nav-logo footer-logo">
          <img src="{{ asset('images/iclimate-logo-light.png') }}" alt="iClimate" class="brand-logo-img">
        </a>
        <p class="footer-tagline">A climate-informed agricultural decision-support platform integrating PAGASA climate data and local rice production records to support farmers, MAO personnel, and IT experts in strategic forecasting, monitoring, and serving Lian, Batangas.</p>
        <div class="footer-tagline-chip">
          <span class="footer-tagline-chip-icon">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M4 10.5l4 4 8-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
          <span>Empowering agriculture through data and technology.</span>
        </div>
      </div>

      <!-- Column 2: Platform -->
      <div class="footer-links">
        <div class="footer-col-label">
          <span class="footer-col-icon">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><rect x="2" y="14" width="4" height="6" rx="1" fill="currentColor"/><rect x="8" y="9" width="4" height="11" rx="1" fill="currentColor" opacity="0.7"/><rect x="14" y="5" width="4" height="15" rx="1" fill="currentColor" opacity="0.45"/></svg>
          </span>
          <h4>Platform</h4>
        </div>
        <ul>
          <li><a href="{{ route('login') }}">Dashboard</a></li>
          <li><a href="#features">Climate Records</a></li>
          <li><a href="#features">Rice Production Records</a></li>
          <li><a href="#features">Reports</a></li>
        </ul>
      </div>

      <!-- Columns 3 & 4: Company + Access side-by-side -->
      <div class="footer-col-pair">
        <div class="footer-links">
          <div class="footer-col-label">
            <span class="footer-col-icon">
              <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M3 8l7-5 7 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 8v9h12V8" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8.5 17v-5h3v5" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
            </span>
            <h4>Company</h4>
          </div>
          <ul>
            <li><a href="#about">About iClimate</a></li>
            <li><a href="#features">Features</a></li>
            <li><a href="#contact">Contact Us</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <div class="footer-col-label">
            <span class="footer-col-icon">
              <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><rect x="5" y="9" width="10" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M7 9V6a3 3 0 0 1 6 0v3" stroke="currentColor" stroke-width="1.5"/></svg>
            </span>
            <h4>Access</h4>
          </div>
          <ul>
            <li><a href="{{ route('login') }}">Login</a></li>
            <li><a href="{{ route('register') }}">Create Account</a></li>
            <li><a href="{{ route('login') }}">Rice Farmer Dashboard</a></li>
            <li><a href="{{ route('login') }}">MAO Personnel Dashboard</a></li>
            <li><a href="{{ route('login') }}">IT Personnel Dashboard</a></li>
          </ul>
        </div>
      </div>

      <!-- Column 5: Follow Us -->
      <div class="footer-social">
        <div class="footer-col-label">
          <span class="footer-col-icon">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="6.5" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M3.5 18c.6-4.8 3-7.5 6.5-7.5s5.9 2.7 6.5 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          </span>
          <h4>Follow Us</h4>
        </div>
        <div class="footer-social-icons">
          <a href="https://www.facebook.com/profile.php?id=61551743430540" class="footer-social-icon" target="_blank" rel="noopener noreferrer" aria-label="Follow iClimate on Facebook (opens in a new tab)" title="Follow us on Facebook">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 21v-7.5h2.5l.4-3H13.5V8.5c0-.9.2-1.5 1.5-1.5h1.6V4.3C16.3 4.2 15.3 4 14.1 4c-2.4 0-4.1 1.5-4.1 4.2V10.5H7.5v3H10V21h3.5z"/></svg>
          </a>
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
        <div class="footer-message-card">
          <p>Together, we build a climate-resilient future for <em>agriculture</em>.</p>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <div class="footer-bottom-brand">
        <span class="footer-bottom-icon">
          <svg width="15" height="15" viewBox="0 0 20 20" fill="none"><path d="M10 2c2.2 3 4 5.3 4 7.5A4 4 0 1 1 6 9.5C6 7.3 7.8 5 10 2z" fill="currentColor"/></svg>
        </span>
        <p>&copy; 2026 iClimate Research Group &mdash; Batangas State University ARASOF-Nasugbu</p>
      </div>
      <span class="footer-badge">
        <svg width="12" height="12" viewBox="0 0 10 12" fill="none"><path d="M5 0C2.24 0 0 2.24 0 5c0 3.75 5 7 5 7s5-3.25 5-7c0-2.76-2.24-5-5-5zm0 6.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" fill="currentColor"/></svg>
        Lian &middot; Batangas &middot; PH
      </span>
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
      el.classList.remove('is-updating'); void el.offsetWidth; el.classList.add('is-updating');
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
      set('[data-hero-rain-label]', 'Rainfall Today');
      set('[data-hero-live]', `Live now · ${w.condition_label || 'Current weather'}${w.updated_label ? ' · ' + w.updated_label : ''}`);
    } catch (e) { /* keep the saved record */ }
  });

  document.addEventListener('DOMContentLoaded', () => {
    const navLinks = document.getElementById('navLinks');
    const hamburger = document.getElementById('hamburger');
    const links = document.querySelectorAll('.nav-link');

    hamburger?.addEventListener('click', () => navLinks?.classList.toggle('open'));
    links.forEach(link => link.addEventListener('click', () => navLinks?.classList.remove('open')));

    const sections = document.querySelectorAll('section[id]');
    const spyObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
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
