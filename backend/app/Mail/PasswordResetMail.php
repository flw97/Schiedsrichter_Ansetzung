<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $url;

    public function __construct(public User $user, public string $token)
    {
        $this->url = rtrim((string) config('app.frontend_url'), '/').'/passwort-zuruecksetzen?'.http_build_query([
            'email' => $user->email,
            'token' => $token,
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Passwort zurücksetzen');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.password-reset');
    }
}
