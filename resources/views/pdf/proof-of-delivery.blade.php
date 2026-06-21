<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* dompdf ships DejaVu Sans — use it so glyphs/accents render reliably */
        * { font-family: "DejaVu Sans", sans-serif; }
        @page { margin: 0; }
        body { margin: 0; color: #1a1d24; font-size: 12px; line-height: 1.5; }

        .bar { background: #0c0d0f; padding: 24px 36px; }
        .bar .brand { font-size: 20px; font-weight: bold; color: #ffffff; }
        .bar .brand span { color: #00e5ff; }
        .bar .doc { font-size: 11px; letter-spacing: 3px; color: #00e5ff; margin-top: 6px; }

        .content { padding: 30px 36px; }

        .badge {
            display: inline-block; background: #e6f9f1; color: #0a7d52;
            border: 1px solid #0a7d52; padding: 5px 14px; border-radius: 4px;
            font-weight: bold; font-size: 11px; letter-spacing: 1px;
        }

        table.details { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.details td { padding: 10px 0; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        td.label {
            color: #6b7280; width: 34%; font-size: 10px;
            text-transform: uppercase; letter-spacing: 0.6px;
        }
        td.value { color: #1a1d24; font-weight: bold; font-size: 12.5px; }

        .photo-wrap { margin-top: 26px; text-align: center; }
        .photo-wrap img { width: 360px; border: 1px solid #d1d5db; border-radius: 6px; }
        .photo-cap { font-size: 10px; color: #6b7280; margin-top: 9px; }

        .foot {
            margin-top: 30px; padding-top: 14px; border-top: 2px solid #0c0d0f;
            font-size: 10px; color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="bar">
        <div class="brand">Fleet<span>Tracker</span></div>
        <div class="doc">PROOF OF DELIVERY</div>
    </div>

    <div class="content">
        <span class="badge">DELIVERED</span>

        <table class="details">
            <tr><td class="label">Tracking Code</td><td class="value">{{ $shipment->tracking_code }}</td></tr>
            <tr><td class="label">Client</td><td class="value">{{ $shipment->client_name }}</td></tr>
            <tr><td class="label">Origin</td><td class="value">{{ $shipment->origin_address ?: '—' }}</td></tr>
            <tr><td class="label">Destination</td><td class="value">{{ $shipment->destination_address ?: '—' }}</td></tr>
            <tr><td class="label">Delivered At</td><td class="value">{{ $deliveredAt }}</td></tr>
            <tr><td class="label">Driver</td><td class="value">{{ $driverName ?: '—' }}</td></tr>
        </table>

        @if($photoData)
            <div class="photo-wrap">
                <img src="{{ $photoData }}" alt="Proof of delivery photo">
                <div class="photo-cap">Photo captured by the driver at the point of delivery.</div>
            </div>
        @else
            <div class="photo-cap" style="margin-top:26px;">No delivery photo was captured for this shipment.</div>
        @endif

        <div class="foot">
            Generated automatically by FleetTracker on {{ $generatedAt }} as confirmation of delivery for the shipment listed above.
        </div>
    </div>
</body>
</html>
