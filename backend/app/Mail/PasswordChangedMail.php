<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $type;

    public function __construct(User $user, string $type = 'manual')
    {
        $this->user = $user;
        $this->type = $type;
    }

    public function envelope(): Envelope
    {
        $subject = $this->type === 'reset' 
            ? 'Votre mot de passe a été réinitialisé'
            : 'Confirmation de changement de mot de passe';
            
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-changed',
        );
    }
}