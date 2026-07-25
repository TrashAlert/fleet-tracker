<?php

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the customer when a shipment has exhausted its delivery attempts and
 * is being returned to sender (terminal). Each earlier attempt is announced by
 * DeliveryAttemptedNotification.
 */
class DeliveryReturnedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Shipment $shipment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $trackingUrl = url('/track?code='.$this->shipment->tracking_code);

        return (new MailMessage)
            ->subject('Shipment Returned to Sender — '.$this->shipment->tracking_code)
            ->greeting('Dear '.$this->shipment->client_name.',')
            ->line('After several unsuccessful delivery attempts, your shipment is being returned to the sender.')
            ->line('**Tracking Code:** '.$this->shipment->tracking_code)
            ->line('**Attempts made:** '.$this->shipment->delivery_attempts)
            ->line('Please contact the sender to arrange redelivery or collection.')
            ->action('View Shipment Status', $trackingUrl);
    }
}
