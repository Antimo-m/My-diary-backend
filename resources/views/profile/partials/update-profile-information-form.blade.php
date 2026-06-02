<section>
    <header class="profile-section-header">
        <span class="profile-section-icon"><i class="bi bi-person"></i></span>
        <div>
        <h2>
            Informazioni profilo
        </h2>

        <p>
            Aggiorna nome ed email collegati al tuo diario personale.
        </p>
        </div>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="profile-form">
        @csrf
        @method('patch')

        <div class="journal-field mb-3">
            <label class="form-label" for="name">Nome</label>
            <input class="form-control" type="text" name="name" id="name" autocomplete="name" value="{{old('name', $user->name)}}" required autofocus>
            @error('name')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $errors->get('name')}}</strong>
            </span>
            @enderror
        </div>

        <div class="journal-field mb-3">
            <label class="form-label" for="email">
                {{__('Email') }}
            </label>

            <input id="email" name="email" type="email" class="form-control" value="{{ old('email', $user->email)}}" required autocomplete="username" />

            @error('email')
            <span class="alert alert-danger mt-2" role="alert">
                <strong>{{ $errors->get('email')}}</strong>
            </span>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div>
                <p class="text-sm mt-2 text-muted">
                    {{ __('Il tuo indirizzo email non e verificato.') }}

                    <button form="send-verification" class="btn btn-outline-dark">
                        {{ __('Invia di nuovo la verifica.') }}
                    </button>
                </p>

                @if (session('status') === 'verification-link-sent')
                <p class="mt-2 text-success">
                    {{ __('Un nuovo link di verifica e stato inviato alla tua email.') }}
                </p>
                @endif
            </div>
            @endif
        </div>

        <div class="d-flex align-items-center gap-4">
            <button class="btn btn-primary" type="submit">Salva profilo</button>

            @if (session('status') === 'profile-updated')
            <p id='profile-status' class="fs-6 text-muted mb-0">{{ __('Salvato.') }}</p>
            @endif
        </div>
    </form>
</section>
