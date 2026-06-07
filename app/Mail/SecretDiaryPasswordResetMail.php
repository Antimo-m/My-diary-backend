<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SecretDiaryPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $url,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Reset password Diario Segreto')
            ->view('emails.secret-diary-password-reset')
            ->text('emails.secret-diary-password-reset-text');
    }
}
