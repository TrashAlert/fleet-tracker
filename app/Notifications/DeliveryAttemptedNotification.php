<?php

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the customer after each failed delivery attempt that is NOT the final
 * one — the parcel goes back into the queue for another try. The final attempt
 * (return to sender) is DeliveryReturnedNotification instead.
 */
class DeliveryAttemptedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly string $reasonLabel,
        public readonly int $attemptsRemaining,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $trackingUrl = url('/track?code='.$this->shipment->tracking_code);

        return (new MailMessage)
            ->subject('Delivery Attempted — '.$this->shipment->tracking_code)
            ->greeting('Dear '.$this->shipment->client_name.',')
            ->line('We tried to deliver your shipment but were unable to complete it.')
            ->line('**Tracking Code:** '.$this->shipment->tracking_code)
            ->line('**Reason:** '.$this->reasonLabel)
            ->line('We will attempt delivery again. You can follow its progress using the link below.')
            ->action('Track Your Shipment', $trackingUrl)
            ->line('If you need to update your delivery details, please contact the sender.');
    }
}
