@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="row g-4 align-items-stretch">
        <div class="col-lg-7">
            <div class="surface h-100">
                <p class="eyebrow mb-2">Dashboard</p>
                <h1 class="h3 fw-bold mb-3">Bentornato, {{ Auth::user()->name }}.</h1>
                <p class="text-secondary mb-4">Diario e Kanban sono ora disponibili nell'app React, mentre Laravel protegge login e API.</p>
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <a class="btn btn-primary" href="{{ env('FRONTEND_URL', 'http://127.0.0.1:5173') }}"><i class="bi bi-journal-text me-2"></i>Apri app React</a>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="surface h-100">
                <h2 class="h5 fw-semibold mb-3">Stato account</h2>
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif
                <p class="text-secondary mb-0">Accesso eseguito. Le tue note e attivita sono visibili solo a te.</p>
            </div>
        </div>
    </div>
</div>
@endsection
