{{-- Flag for the language switch: 'fil' = Philippines (Filipino), 'en' = United States (English).
     Drawn as SVG because Windows does not show flag emoji. The button around it carries the language name. --}}
@if (($code ?? 'en') === 'fil')
    <svg class="ic-flag" width="24" height="16" viewBox="0 0 24 16" aria-hidden="true" focusable="false">
        <rect width="24" height="8" fill="#0038A8"/>
        <rect y="8" width="24" height="8" fill="#CE1126"/>
        <path d="M0 0 13.86 8 0 16Z" fill="#fff"/>
        <circle cx="5" cy="8" r="1.9" fill="#FCD116"/>
        <g fill="#FCD116">
            <circle cx="1.6" cy="2.3" r=".75"/>
            <circle cx="1.6" cy="13.7" r=".75"/>
            <circle cx="10.6" cy="8" r=".75"/>
        </g>
    </svg>
@else
    <svg class="ic-flag" width="24" height="16" viewBox="0 0 24 16" aria-hidden="true" focusable="false">
        <rect width="24" height="16" fill="#fff"/>
        <g fill="#B22234">
            <rect width="24" height="1.23"/>
            <rect y="2.46" width="24" height="1.23"/>
            <rect y="4.92" width="24" height="1.23"/>
            <rect y="7.38" width="24" height="1.23"/>
            <rect y="9.85" width="24" height="1.23"/>
            <rect y="12.31" width="24" height="1.23"/>
            <rect y="14.77" width="24" height="1.23"/>
        </g>
        <rect width="10.4" height="8.6" fill="#3C3B6E"/>
        <g fill="#fff">
            <circle cx="2" cy="1.8" r=".5"/><circle cx="5.2" cy="1.8" r=".5"/><circle cx="8.4" cy="1.8" r=".5"/>
            <circle cx="3.6" cy="4.3" r=".5"/><circle cx="6.8" cy="4.3" r=".5"/>
            <circle cx="2" cy="6.8" r=".5"/><circle cx="5.2" cy="6.8" r=".5"/><circle cx="8.4" cy="6.8" r=".5"/>
        </g>
    </svg>
@endif
