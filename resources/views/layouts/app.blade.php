<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>My Diary - @yield('title', 'Home')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/my-diary-logo.svg') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>

<body>
    <div id="app" class="min-vh-100 d-flex flex-column app-shell">
        <nav class="navbar navbar-expand-lg navbar-light sticky-top app-navbar">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="{{ route('home') }}">
                    <img class="brand-logo" src="{{ asset('images/my-diary-logo.svg') }}" alt="" aria-hidden="true">
                    <span>My Diary</span>
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="mainNavbar">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}" title="Home">Home</a>
                        </li>
                        @auth
                            <li class="nav-item">
                                <a class="nav-link" href="{{ env('FRONTEND_URL', 'http://127.0.0.1:5173') }}" title="Apri app React">App React</a>
                            </li>
                        @endauth
                    </ul>

                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                        @guest
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">Login</a>
                            </li>
                            @if (Route::has('register'))
                                <li class="nav-item ms-lg-2">
                                    <a class="btn btn-primary btn-sm px-3" href="{{ route('register') }}">Registrati</a>
                                </li>
                            @endif
                        @else
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ Auth::user()->name }}
                                </a>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="{{ route('dashboard') }}">Dashboard</a>
                                    <a class="dropdown-item" href="{{ route('profile.edit') }}">Profilo</a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        Logout
                                    </a>
                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <main class="flex-grow-1 app-main">
            @yield('content')
        </main>

        <div class="confirm-modal-backdrop d-none" data-confirm-modal>
            <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
                <div class="confirm-modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
                <h2 id="confirm-modal-title">Confermi l'azione?</h2>
                <p data-confirm-message>Questa operazione non puo essere annullata.</p>
                <div class="confirm-modal-actions">
                    <button class="btn btn-subtle" type="button" data-confirm-cancel>Annulla</button>
                    <button class="btn btn-danger-soft" type="button" data-confirm-accept>Elimina</button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
