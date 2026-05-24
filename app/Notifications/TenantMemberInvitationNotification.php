<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantMemberInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $tenantName,
        private readonly string $inviterName,
        private readonly string $role,
        private readonly string $inviteToken,
        private readonly CarbonInterface $expiresAt,
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
            ->subject('You have been invited to Bunshin AI')
            ->line($this->inviterName.' invited you to join '.$this->tenantName.' on Bunshin AI.')
            ->line('Role: '.$this->role)
            ->line('Invitation token: '.$this->inviteToken)
            ->line('This invitation expires at '.$this->expiresAt->toAtomString().'.')
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }

    public function tenantName(): string
    {
        return $this->tenantName;
    }

    public function inviterName(): string
    {
        return $this->inviterName;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function inviteToken(): string
    {
        return $this->inviteToken;
    }

    public function expiresAt(): CarbonInterface
    {
        return $this->expiresAt;
    }
}
