<?php

namespace App\Mail;

use App\Models\Activity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ActivityCancelled extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Activity $actividad)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu actividad fue cancelada');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.activity.cancelled', with: ['actividad' => $this->actividad]);
    }
}
