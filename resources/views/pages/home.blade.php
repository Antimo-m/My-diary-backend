@extends('layouts.app')

@section('title', 'Home')

@section('content')
<section class="container py-4 py-lg-5">
    <div class="row align-items-center g-4 g-lg-5">
        <div class="col-lg-7">
            <p class="eyebrow mb-3">Diario digitale personale</p>
            <h1 class="display-4 fw-bold mb-3">Scrivi la giornata, organizza le attivita, ritrova il filo.</h1>
            <p class="lead text-secondary mb-4">My Diary unisce note private, pagine visive e una bacheca Kanban fluida per dare forma alla tua giornata.</p>
            <div class="d-flex flex-column flex-sm-row gap-2">
                @auth
                    <a class="btn btn-primary btn-lg" href="{{ env('FRONTEND_URL', 'http://127.0.0.1:5173') }}"><i class="bi bi-pencil-square me-2"></i>Apri app React</a>
                @else
                    <a class="btn btn-primary btn-lg" href="{{ route('register') }}"><i class="bi bi-person-plus me-2"></i>Inizia ora</a>
                    <a class="btn btn-outline-secondary btn-lg" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right me-2"></i>Accedi</a>
                @endauth
            </div>
        </div>
        <div class="col-lg-5">
            <div class="preview-panel elevated-panel">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="fw-semibold">Oggi</span>
                    <span class="badge text-bg-light">{{ now()->format('d/m/Y') }}</span>
                </div>
                <div class="preview-note mb-3">
                    <span class="text-primary fw-semibold small">Nota</span>
                    <h2 class="h5 mt-2">Mattina produttiva</h2>
                    <p class="mb-0 text-secondary">Annota pensieri, priorita e piccoli dettagli della giornata in uno spazio riservato.</p>
                </div>
                <div class="row g-2">
                    <div class="col-4"><div class="mini-column">Da fare</div></div>
                    <div class="col-4"><div class="mini-column active">In corso</div></div>
                    <div class="col-4"><div class="mini-column done">Completato</div></div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
