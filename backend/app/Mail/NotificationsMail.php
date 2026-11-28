<?php

namespace App\Mail;

use App\Models\PushMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class NotificationsMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $appUrl;

    /** @param Collection<int, PushMessage> $messages */
    public function __construct(public User $user, public Collection $messages)
    {
        $this->appUrl = rtrim((string) config('app.frontend_url'), '/');
    }

    public function envelope(): Envelope
    {
        $count = $this->messages->count();

        return new Envelope(subject: $count === 1 ? 'Neue Benachrichtigung' : "Neue Benachrichtigungen ({$count})");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.notifications');
    }
}
