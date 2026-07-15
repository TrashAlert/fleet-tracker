<?php

namespace App\Http\Controllers;

use App\Models\ShipmentTicket;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shipment requests: a customer (anonymous, no account) asks for a NEW shipment
 * from the public /track home screen. On submit they are shown a short
 * request_code to read to staff at the counter; admin/manager look it up on the
 * fleet "Shipment Requests" page and approve or deny.
 *
 * store() is PUBLIC (throttled) — everything else is fleet-side, admin/manager.
 * Approval is not an action here: it happens implicitly when the manager
 * creates the real shipment from the prefilled form (storeShipment + ticket_id),
 * so there is no approved-request-without-shipment state.
 */
class ShipmentTicketController extends Controller
{
    /**
     * Public: customer submits a new-shipment request from the /track landing.
     * Traditional form POST — redirects back to /track flashing the request
     * code on success, or with validation errors (the form card re-renders).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('ticket', [
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'client_phone' => 'nullable|string|max:20',
            'destination_address' => 'required|string|max:500',
            'delivery_notes' => 'nullable|string|max:1000',
            'delivery_tier' => 'required|in:'.implode(',', array_keys(config('fleet.delivery_tiers', []))),
        ]);

        $ticket = ShipmentTicket::create($data);

        ActivityLogger::logEvent(
            'shipment_requested',
            "Shipment request {$ticket->request_code} submitted by {$ticket->client_name} — destination: {$ticket->destination_address}",
            'ShipmentTicket', $ticket->id, $ticket->request_code,
            ['client_email' => $ticket->client_email]
        );

        return redirect('/track')->with('request_code', $ticket->request_code);
    }

    /**
     * Fleet: review queue (admin/manager). ?q= searches by the request code the
     * customer shows at the counter (also matches customer name).
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $q = trim((string) $request->query('q', ''));

        $query = ShipmentTicket::with(['reviewer', 'createdShipment'])->latest();
        if (in_array($status, ['pending', 'approved', 'denied'], true)) {
            $query->where('status', $status);
        }
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('request_code', 'like', '%'.strtoupper($q).'%')
                    ->orWhere('client_name', 'like', "%{$q}%");
            });
        }

        $tickets = $query->paginate(20)->withQueryString();

        $statusCounts = ShipmentTicket::selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status')->toArray();

        return view('fleet.tickets', compact('tickets', 'statusCounts'));
    }

    /**
     * Fleet: request JSON for prefilling the New Shipment form (admin/manager).
     * Origin is deliberately absent — the manager picks the pickup warehouse.
     */
    public function show(ShipmentTicket $ticket): JsonResponse
    {
        return response()->json([
            'id' => $ticket->id,
            'request_code' => $ticket->request_code,
            'status' => $ticket->status,
            'client_name' => $ticket->client_name,
            'client_email' => $ticket->client_email,
            'client_phone' => $ticket->client_phone,
            'destination_address' => $ticket->destination_address,
            'delivery_notes' => $ticket->delivery_notes,
            'delivery_tier' => $ticket->delivery_tier,
            'delivery_tier_label' => config("fleet.delivery_tiers.{$ticket->delivery_tier}.label")
                ?? ucfirst($ticket->delivery_tier ?? 'standard'),
        ]);
    }

    /**
     * Fleet: deny a pending request (admin/manager). No customer email — by design.
     */
    public function deny(Request $request, ShipmentTicket $ticket): JsonResponse
    {
        if ($ticket->status !== 'pending') {
            return response()->json(['error' => 'This request has already been reviewed.'], 422);
        }

        $ticket->update([
            'status' => 'denied',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        ActivityLogger::logEvent(
            'ticket_denied',
            "Shipment request {$ticket->request_code} denied by ".auth()->user()->name,
            'ShipmentTicket', $ticket->id, $ticket->request_code,
            ['reviewed_by' => auth()->user()->name]
        );

        return response()->json(['ok' => true, 'status' => $ticket->status]);
    }
}
