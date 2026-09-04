<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewManagerCredentials extends Mailable
{
    use Queueable, SerializesModels;

    public User $manager;
    public string $tempPassword;

    public function __construct(User $manager, string $tempPassword)
    {
        $this->manager = $manager;
        $this->tempPassword = $tempPassword;
    }

    public function build()
    {
        return $this->subject('Vos identifiants - Agent IA Rédaction Web')
                   ->view('emails.new-manager-credentials');
    }
}