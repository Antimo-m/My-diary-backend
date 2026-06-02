@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="auth-shell mx-auto">
            {{ $slot ?? '' }}
            @yield('guest-content')
        </div>
    </div>
@endsection
