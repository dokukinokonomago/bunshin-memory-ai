<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailChangeNotification extends Notification
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
            ->subject('Verify your new email address')
            ->line('Please verify this email address to complete your Bunshin AI account email change.')
            ->action('Verify email address', $this->verificationUrl())
            ->line('If you did not request this change, you can ignore this email.');
    }

    public function email(): string
    {
        return $this->email;
    }

    public function verificationUrl(): string
    {
        return URL::temporarySignedRoute(
            'api.v1.auth.email.change.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'id' => $this->userId,
                'hash' => sha1($this->email),
            ],
        );
    }
}
