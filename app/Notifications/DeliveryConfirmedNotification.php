<?php

namespace App\Notifications;

use App\Models\Shipment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class DeliveryConfirmedNotification extends Notification
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
        $deliveredAt = ($shipment->actual_delivery_at ?? now())->format('d M Y, H:i');

        // Embed the driver's proof photo as a base64 data URI. dompdf allows the
        // data:// protocol by default, so no remote loading needs enabling.
        $photoData = null;
        $path = $shipment->delivery_photo_path;
        if ($path && Storage::disk('public')->exists($path)) {
            $mime      = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';
            $photoData = 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($path));
        }

        // Render the proof-of-delivery PDF.
        $pdf = Pdf::loadView('pdf.proof-of-delivery', [
            'shipment'    => $shipment,
            'deliveredAt' => $deliveredAt,
            'driverName'  => $shipment->vehicle?->driver_name,
            'photoData'   => $photoData,
            'generatedAt' => now()->format('d M Y, H:i'),
        ]);

        return (new MailMessage)
            ->subject('Delivery Confirmed — ' . $shipment->tracking_code)
            ->greeting('Dear ' . $shipment->client_name . ',')
            ->line('Good news — your shipment has been delivered.')
            ->line('**Tracking Code:** ' . $shipment->tracking_code)
            ->line('**Delivered At:** ' . $deliveredAt)
            ->line('**Delivered To:** ' . $shipment->destination_address)
            ->line('A proof of delivery, including the photo taken at handover, is attached as a PDF.')
            ->action('View Delivery Details', $trackingUrl)
            ->line('Thank you for shipping with FleetTracker.')
            ->attachData($pdf->output(), 'proof-of-delivery-' . $shipment->tracking_code . '.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
