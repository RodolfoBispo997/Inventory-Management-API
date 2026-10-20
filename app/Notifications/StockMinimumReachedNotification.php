<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockMinimumReachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $productName,
        public string $sku,
        public int $currentStock,
        public int $minimumStock,
    ) {
        $this->afterCommit();
    }

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
            ->subject('Estoque mínimo atingido')
            ->greeting('Alerta de estoque')
            ->line("O produto {$this->productName} (SKU {$this->sku}) atingiu o estoque mínimo.")
            ->line("Saldo atual: {$this->currentStock}. Estoque mínimo: {$this->minimumStock}.");
    }
}
