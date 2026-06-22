<?php

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShipmentCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Shipment $shipment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $shipment    = $this->shipment;
        $trackingUrl = url('/track?code=' . $shipment->tracking_code);

        $mail = (new MailMessage)
            ->subject('Your Tracking Code — ' . $shipment->tracking_code)
            ->greeting('Dear ' . $shipment->client_name . ',')
            ->line('Your shipment has been registered and is being prepared for delivery.')
            ->line('**Tracking Code:** ' . $shipment->tracking_code)
            ->line('**Destination:** ' . $shipment->destination_address);

        if ($shipment->expected_delivery_at) {
            $mail->line('**Estimated Delivery:** ' . $shipment->expected_delivery_at->format('d M Y, H:i'));
        }

        return $mail
            ->action('Track Your Shipment', $trackingUrl)
            ->line('Use the link above to follow your delivery on a live map at any time.')
            ->line('Thank you for shipping with FleetTracker.');
    }
}
