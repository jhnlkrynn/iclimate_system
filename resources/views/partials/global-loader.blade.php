{{-- iClimate global loading overlay: one component for every page and role (see public/js/iclimate-loader.js). --}}
<link rel="stylesheet" href="{{ asset('css/iclimate-loader.css') }}?v={{ @filemtime(public_path('css/iclimate-loader.css')) }}">
<script src="{{ asset('js/iclimate-loader.js') }}?v={{ @filemtime(public_path('js/iclimate-loader.js')) }}"></script>
