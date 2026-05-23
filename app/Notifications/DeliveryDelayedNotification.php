<?php

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliveryDelayedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Shipment $shipment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $trackingUrl = url('/track?code=' . $this->shipment->tracking_code);

        return (new MailMessage)
            ->subject('Shipment Delay Notice — ' . $this->shipment->tracking_code)
            ->greeting('Dear ' . $this->shipment->client_name . ',')
            ->line('We regret to inform you that your shipment is experiencing a delay.')
            ->line('**Tracking Code:** ' . $this->shipment->tracking_code)
            ->line('**Original Expected Delivery:** ' . $this->shipment->expected_delivery_at->format('d M Y, H:i'))
            ->line('Our fleet team is working to minimise the delay. You can monitor your shipment in real time using the link below.')
            ->action('Track Your Shipment', $trackingUrl)
            ->line('We apologise for any inconvenience caused.');
    }
}
