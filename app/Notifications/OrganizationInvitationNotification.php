<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification
{
    public function __construct(
        public string $organizationName,
        public string $token,
        public string $acceptanceEndpoint,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Convite para {$this->organizationName}")
            ->greeting('Você recebeu um convite')
            ->line("Você foi convidado para participar de {$this->organizationName} como operador.")
            ->line("Token de aceite: {$this->token}")
            ->line("Envie o token, seu nome e uma senha para POST {$this->acceptanceEndpoint}.")
            ->line('O convite expira em sete dias e pode ser usado uma única vez.');
    }
}
