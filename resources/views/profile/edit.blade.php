@extends('layouts.app')
@section('content')

<div class="profile-page">
    <div class="container py-4 py-lg-5">
        <header class="profile-hero">
            <div class="profile-avatar" aria-hidden="true">
                {{ strtoupper(mb_substr($user->name, 0, 1)) }}
            </div>
            <div>
                <p class="eyebrow mb-2">Spazio personale</p>
                <h1 class="page-title mb-2">Il tuo profilo</h1>
                <p class="text-secondary mb-0">Gestisci identita, accesso e sicurezza del tuo diario.</p>
            </div>
        </header>

        <div class="profile-grid">
            <aside class="profile-memory-card">
                <span class="profile-memory-icon"><i class="bi bi-stars"></i></span>
                <h2>Memorie protette</h2>
                <p>Un account ordinato rende piu semplice custodire pagine, ricordi e piccoli progressi quotidiani.</p>
            </aside>

            <div class="profile-stack">
                <div class="profile-panel">
                    @include('profile.partials.update-profile-information-form')
                </div>

                <div class="profile-panel">
                    @include('profile.partials.update-password-form')
                </div>

                <div class="profile-panel profile-panel-danger">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
