<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $initialPassword,
        public string $loginUrl
    ) {
    }

    public function build()
    {
        return $this->subject('コキャッチのログイン情報')
            ->view('emails.user_invitation');
    }
}
