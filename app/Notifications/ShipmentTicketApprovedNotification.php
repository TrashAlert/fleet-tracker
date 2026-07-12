<?php

namespace App\Notifications;

use App\Models\Shipment;
use App\Models\ShipmentTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the requesting customer when their shipment request is approved —
 * i.e. the manager created the real shipment from the prefilled form. Routed
 * via the ticket's own client_email (ShipmentTicket is Notifiable).
 */
class ShipmentTicketApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ShipmentTicket $ticket,
        public readonly Shipment $newShipment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->ticket;
        $shipment = $this->newShipment;

        // Public tracking links must use the configured base URL, never the
        // server's own origin (clients cannot reach LAN URLs).
        $base = rtrim(config('fleet.tracking_base_url', 'https://fleet-tracker.xyz'), '/');
        $trackingUrl = $base.'/track?code='.$shipment->tracking_code;

        return (new MailMessage)
            ->subject('Shipment Request Approved — '.$shipment->tracking_code)
            ->greeting('Dear '.$ticket->client_name.',')
            ->line("Your shipment request (code {$ticket->request_code}) has been approved.")
            ->line('A shipment has been created for it:')
            ->line('**Tracking Code:** '.$shipment->tracking_code)
            ->line('**Destination:** '.$shipment->destination_address)
            ->action('Track Your Shipment', $trackingUrl)
            ->line('Use the link above to follow the delivery on a live map at any time.')
            ->line('Thank you for shipping with FleetTracker.');
    }
}
