<nav class="auth-toggle" aria-label="Sign in or create an account">
  <a href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Sign in</a>
  <a href="{{ route('register') }}" @if(request()->routeIs('register')) aria-current="page" @endif>Create account</a>
</nav>
