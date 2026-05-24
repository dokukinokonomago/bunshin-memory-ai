<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class SecretUnlockPasswordRecoveryNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $userId,
        private readonly string $email,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Recover your secret unlock password')
            ->line('Use this signed link to continue your Bunshin AI secret unlock password recovery.')
            ->action('Recover secret unlock password', $this->recoveryUrl())
            ->line('If you did not request this recovery, you can ignore this email.');
    }

    public function recoveryUrl(): string
    {
        return URL::temporarySignedRoute(
            'api.v1.secret-unlock-password.recovery.complete',
            now()->addMinutes((int) config('bunshin.security.secret_unlock_password_recovery.expires_minutes', 30)),
            [
                'id' => $this->userId,
                'hash' => sha1($this->email),
            ],
        );
    }
}
