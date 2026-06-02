<section>
    <header class="profile-section-header">
        <span class="profile-section-icon"><i class="bi bi-shield-lock"></i></span>
        <div>
        <h2>
            Sicurezza
        </h2>

        <p>
            Mantieni protetto l'accesso alle tue pagine private.
        </p>
        </div>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="profile-form">
        @csrf
        @method('put')

        <div class="journal-field mb-3">
            <label class="form-label" for="current_password">Password attuale</label>
            <input class="mt-1 form-control" type="password" name="current_password" id="current_password" autocomplete="current-password">
            @error('current_password')
            <span class="invalid-feedback mt-2" role="alert">
                <strong>{{ $errors->updatePassword->get('current_password') }}</strong>
            </span>
            @enderror
        </div>

        <div class="journal-field mb-3">
            <label class="form-label" for="password">Nuova password</label>
            <input class="mt-1 form-control" type="password" name="password" id="password" autocomplete="new-password">
            @error('password')
            <span class="invalid-feedback mt-2" role="alert">
                <strong>{{ $errors->updatePassword->get('password')}}</strong>
            </span>
            @enderror
        </div>

        <div class="journal-field mb-3">

            <label class="form-label" for="password_confirmation">Conferma password</label>
            <input class="mt-2 form-control" type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password">
            @error('password_confirmation')
            <span class="invalid-feedback mt-2" role="alert">
                <strong>{{ $errors->updatePassword->get('password_confirmation')}}</strong>
            </span>
            @enderror
        </div>

        <div class="d-flex align-items-center gap-4">
            <button type="submit" class="btn btn-primary">Aggiorna password</button>

            @if (session('status') === 'password-updated')
            <p id='status' class="fs-6 text-muted mb-0">{{ __('Salvato.') }}</p>
            @endif
        </div>
    </form>
</section>
