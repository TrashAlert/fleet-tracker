<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- SECURITY: the tracking code lives in the URL query string. Without
         this, EVERY third-party request (OSM tiles, fonts, CDN) and every
         outbound click leaks the code via the Referer header. --}}
    <meta name="referrer" content="no-referrer">
    {{-- SECURITY: tracking pages must never be indexed by search engines. --}}
    <meta name="robots" content="noindex, nofollow">

    <title>Track Your Shipment — FleetTrack</title>

    {{-- SECURITY: Subresource Integrity pins the exact Leaflet build — a
         compromised CDN cannot execute arbitrary JS on this public page.
         (Hashes are the official Leaflet 1.9.4 values from leafletjs.com.) --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #f4f3ef;
            --surface: #ffffff;
            --border: #e2e0d8;
            --text: #1a1a1a;
            --subtle: #6b6b6b;
            --accent: #1a1a2e;
            --accent2: #ff6b35;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'JetBrains Mono', monospace;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Header ── */
        .header {
            background: var(--accent);
            padding: 20px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .wordmark {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 20px;
            color: #00e5ff;
            letter-spacing: -0.5px;
        }
        .wordmark span { color: #fff; font-weight: 400; font-size: 13px; margin-left: 10px; }

        /* ── Search ── */
        .search-wrap {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 24px 40px;
        }
        .search-label {
            font-size: 11px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--subtle);
            margin-bottom: 10px;
        }
        .search-row { display: flex; gap: 10px; max-width: 560px; }
        .search-input {
            flex: 1;
            border: 2px solid var(--border);
            background: var(--bg);
            padding: 11px 16px;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            color: var(--text);
            letter-spacing: 0.05em;
            text-transform: uppercase;
            outline: none;
            transition: border-color 0.15s;
        }
        .search-input:focus { border-color: var(--accent); }
        /* ── GlareHover (React Bits) — CSS-only port, applied directly to the
              button. The React wrapper only computed CSS variables, so the
              effect transfers verbatim: an angled cyan glare sweeps corner to
              corner. Also fires on :active (touch) and :focus-visible (keys). ── */
        .search-btn {
            --gh-angle: -45deg;
            --gh-duration: 650ms;
            --gh-size: 250%;
            --gh-rgba: rgba(0, 229, 255, 0.35);   /* fleet cyan glare */
            position: relative;
            overflow: hidden;
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 11px 22px;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .search-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(
                var(--gh-angle),
                hsla(0, 0%, 0%, 0) 60%,
                var(--gh-rgba) 70%,
                hsla(0, 0%, 0%, 0),
                hsla(0, 0%, 0%, 0) 100%
            );
            background-size: var(--gh-size) var(--gh-size), 100% 100%;
            background-repeat: no-repeat;
            background-position: -100% -100%, 0 0;
            transition: background-position var(--gh-duration) ease;
            pointer-events: none;
        }
        .search-btn:hover::before,
        .search-btn:focus-visible::before,
        .search-btn:active::before {
            background-position: 100% 100%, 0 0;
        }
        @media (prefers-reduced-motion: reduce) {
            .search-btn::before { transition: none; }
        }

        /* ── Layout ── */
        .container {
            max-width: 1100px;
            width: 100%;
            margin: 0 auto;
            padding: 28px 40px;
            flex: 1;
        }

        .not-found { text-align: center; padding: 80px 20px; color: var(--subtle); }
        .not-found .code { font-size: 48px; font-weight: 800; color: var(--border); font-family: 'Syne', sans-serif; }

        /* ── Status stepper ── */
        .stepper {
            display: flex;
            align-items: flex-start;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px 24px 16px;
            margin-bottom: 20px;
        }
        .step { flex: 1; text-align: center; position: relative; }
        .step-dot {
            width: 26px; height: 26px; border-radius: 50%;
            background: var(--bg); border: 2px solid var(--border);
            margin: 0 auto 8px; position: relative; z-index: 1;
            display: flex; align-items: center; justify-content: center;
        }
        .step-dot svg { width: 13px; height: 13px; stroke: var(--surface); display: none; }
        .step-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--subtle); }
        .step-sub   { font-size: 10px; color: var(--subtle); margin-top: 3px; min-height: 13px; }
        /* connector line */
        .step:not(:first-child)::before {
            content: ''; position: absolute;
            top: 12px; left: -50%; width: 100%; height: 2px;
            background: var(--border);
        }
        .step.done .step-dot { background: var(--success); border-color: var(--success); }
        .step.done .step-dot svg { display: block; }
        .step.done:not(:first-child)::before { background: var(--success); }
        .step.done .step-label { color: var(--success); font-weight: 500; }
        .step.current .step-dot { border-color: var(--accent2); background: var(--accent2); animation: stepPulse 2s infinite; }
        .step.current .step-label { color: var(--accent2); font-weight: 500; }
        .step.warn .step-dot { background: var(--warning); border-color: var(--warning); }
        .step.warn .step-label { color: var(--warning); font-weight: 500; }
        .step.cancel .step-dot { background: var(--danger); border-color: var(--danger); }
        .step.cancel .step-label { color: var(--danger); font-weight: 500; }
        @keyframes stepPulse { 0%,100% { box-shadow: 0 0 0 0 rgba(255,107,53,0.35); } 50% { box-shadow: 0 0 0 7px rgba(255,107,53,0); } }

        /* ── Cards ── */
        .shipment-layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 20px;
            align-items: start;
        }
        .info-card, .map-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        .info-card-header { background: var(--accent); padding: 20px 22px; color: #fff; }
        .tracking-code-label { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; opacity: 0.6; }
        .tracking-code-value { font-size: 22px; font-weight: 700; margin-top: 4px; letter-spacing: 0.05em; color: #00e5ff; }

        .status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 20px;
            font-size: 11px; font-weight: 500;
            letter-spacing: 0.05em; text-transform: uppercase;
            margin-top: 12px;
        }
        .status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .status-transit   { background: #1a2a4a; color: #60a5fa; }
        .status-delayed   { background: #3a2a10; color: #f59e0b; }
        .status-delivered { background: #14291e; color: #22c55e; }
        .status-pending   { background: #2a2a2a; color: #9ca3af; }

        .info-body { padding: 20px 22px; }
        .info-row { display: flex; flex-direction: column; gap: 3px; padding: 12px 0; border-bottom: 1px solid var(--border); }
        .info-row:last-child { border-bottom: none; }
        .info-row-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--subtle); }
        .info-row-value { font-size: 13px; color: var(--text); line-height: 1.5; }

        .map-card-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
            font-size: 12px; gap: 10px;
        }
        .map-card-title { font-family: 'Syne', sans-serif; font-weight: 700; }
        /* z-index containment: nothing inside the map can paint above page chrome */
        .map-wrap { position: relative; z-index: 0; }
        #client-map { height: 500px; }

        .live-dot { display: inline-flex; align-items: center; gap: 5px; font-size: 10px; color: var(--success); }
        .live-dot::before {
            content: ''; width: 7px; height: 7px;
            background: var(--success); border-radius: 50%;
            animation: pulse 2s infinite;
        }
        .conn-pill {
            display: none; font-size: 10px; color: var(--warning);
            border: 1px solid var(--warning); border-radius: 20px; padding: 3px 10px;
        }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }

        .privacy-note {
            margin-top: 12px;
            font-size: 10px; color: var(--subtle);
            display: flex; gap: 7px; align-items: flex-start; line-height: 1.5;
        }
        .privacy-note svg { flex-shrink: 0; margin-top: 1px; }

        .footer {
            text-align: center; padding: 18px;
            font-size: 10px; color: var(--subtle);
            border-top: 1px solid var(--border);
        }

        /* ── Forwarding request card ── */
        .fwd-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; margin-bottom: 20px; overflow: hidden;
        }
        .fwd-card summary {
            list-style: none; cursor: pointer; padding: 14px 22px;
            display: flex; align-items: center; gap: 10px;
            font-family: 'Syne', sans-serif; font-weight: 700; font-size: 14px;
        }
        .fwd-card summary::-webkit-details-marker { display: none; }
        .fwd-card summary svg { color: var(--accent2); flex-shrink: 0; }
        .fwd-cta {
            margin-left: auto; flex-shrink: 0;
            background: var(--accent2); color: #fff;
            border-radius: 8px; padding: 7px 14px;
            font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 600;
        }
        .fwd-card[open] .fwd-cta { display: none; }
        .fwd-chevron { flex-shrink: 0; transition: transform 0.15s; color: var(--subtle); }
        .fwd-card:not([open]) .fwd-chevron { display: none; }
        .fwd-card[open] .fwd-chevron { margin-left: auto; }
        .fwd-card[open] .fwd-chevron { transform: rotate(180deg); }
        .fwd-body { padding: 0 22px 22px; border-top: 1px solid var(--border); }
        .fwd-intro { font-size: 12px; color: var(--subtle); margin: 14px 0 16px; }
        .fwd-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .fwd-field { display: flex; flex-direction: column; gap: 5px; }
        .fwd-field.full { grid-column: 1 / -1; }
        .fwd-label {
            font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase;
            color: var(--subtle); font-weight: 600;
        }
        .fwd-input {
            border: 2px solid var(--border); background: var(--bg);
            padding: 10px 13px; border-radius: 8px;
            font-family: 'JetBrains Mono', monospace; font-size: 13px;
            color: var(--text); outline: none; transition: border-color 0.15s;
            width: 100%;
        }
        .fwd-input:focus { border-color: var(--accent); }
        textarea.fwd-input { resize: vertical; min-height: 60px; }
        .fwd-errors {
            background: rgba(220, 53, 69, 0.08); border: 1px solid rgba(220, 53, 69, 0.3);
            border-radius: 8px; padding: 10px 14px; font-size: 12px;
            color: var(--danger); margin: 14px 0 0;
        }
        .fwd-errors li { margin-left: 16px; }
        .fwd-note {
            display: flex; gap: 10px; align-items: flex-start;
            padding: 16px 22px; font-size: 12px; color: var(--subtle);
        }
        .fwd-note svg { flex-shrink: 0; margin-top: 1px; color: var(--warning); }
        .fwd-note.success svg { color: var(--success); }
        .fwd-fineprint { font-size: 10px; color: var(--subtle); margin-top: 10px; }
        .fwd-tiers { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .fwd-tier { position: relative; cursor: pointer; }
        .fwd-tier input { position: absolute; opacity: 0; pointer-events: none; }
        .fwd-tier-body {
            display: block; border: 2px solid var(--border); border-radius: 8px;
            padding: 11px 14px; transition: border-color 0.15s, background 0.15s;
        }
        .fwd-tier input:checked + .fwd-tier-body {
            border-color: var(--accent);
            background: color-mix(in srgb, var(--accent) 5%, transparent);
        }
        .fwd-tier input:focus-visible + .fwd-tier-body { outline: 2px solid var(--accent); outline-offset: 2px; }
        .fwd-tier-name { display: block; font-family: 'Syne', sans-serif; font-weight: 700; font-size: 13px; }
        .fwd-tier-days { display: block; font-size: 10px; color: var(--subtle); margin-top: 2px; }
        .fwd-code {
            font-family: 'JetBrains Mono', monospace; font-size: 36px; font-weight: 800;
            letter-spacing: 0.18em; color: var(--accent2); margin-top: 8px;
        }

        @media (max-width: 768px) {
            .container { padding: 20px; }
            .search-wrap { padding: 20px; }
            .header { padding: 16px 20px; }
            .shipment-layout { grid-template-columns: 1fr; }
            #client-map { height: 340px; }
            .stepper { padding: 16px 10px 12px; }
            .step-sub { display: none; }
            .fwd-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header class="header">
    <div>
        <div class="wordmark">FleetTrack <span>/ Shipment Tracker</span></div>
    </div>
</header>

<div class="search-wrap">
    <div class="search-label">Enter your tracking code</div>
    <form method="GET" action="/track">
        <div class="search-row">
            <input class="search-input" name="code" placeholder="e.g. AB12CD34EF"
                   value="{{ $code ?? '' }}" autocomplete="off" maxlength="10"
                   pattern="[A-Za-z0-9]{10}" inputmode="text" spellcheck="false"
                   title="Tracking codes are 10 letters and numbers"
                   aria-label="Tracking code">
            <button class="search-btn" type="submit">
                Track
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </div>
    </form>
</div>

<div class="container">

    @if(! $code)
        @if(session('request_code'))
            {{-- Request submitted — show the counter code, large --}}
            <div class="fwd-card" style="text-align:center; padding:36px 24px;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2" style="display:block; margin:0 auto 14px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <div style="font-family:'Syne',sans-serif; font-weight:700; font-size:16px;">Request submitted</div>
                <div style="font-size:12px; color:var(--subtle); margin-top:6px;">Your request code:</div>
                <div class="fwd-code">{{ session('request_code') }}</div>
                <p style="font-size:12px; color:var(--subtle); max-width:420px; margin:14px auto 0;">
                    Show this code to our staff at the counter. You'll also receive an email
                    at the address you provided if your request is approved.
                </p>
            </div>
        @else
            {{-- Landing --}}
            <div class="not-found" style="padding-bottom:40px;">
                <div style="margin-bottom:16px;">
                    <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="var(--border)" stroke-width="1.5" style="display:block;margin:0 auto;">
                        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                </div>
                <p style="font-size:14px; color:var(--subtle);">Enter your 10-character tracking code above to see your shipment status.</p>
            </div>

            {{-- ── Request a new shipment (no account needed) ── --}}
            <details class="fwd-card" @if($errors->ticket->any()) open @endif>
                <summary>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><line x1="12" y1="22.08" x2="12" y2="12"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/></svg>
                    Want to send something?
                    <span class="fwd-cta">Request a shipment</span>
                    <svg class="fwd-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </summary>
                <div class="fwd-body">
                    <p class="fwd-intro">
                        Tell us where your goods should go and how to reach you. When you submit,
                        you'll get a request code to show our staff — our team will review the
                        request, and you'll be emailed if it is approved.
                    </p>

                    @if($errors->ticket->any())
                        <ul class="fwd-errors" style="margin-bottom:14px;">
                            @foreach($errors->ticket->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <form method="POST" action="{{ route('client.track.request') }}">
                        @csrf
                        <div class="fwd-grid">
                            <div class="fwd-field">
                                <label class="fwd-label" for="fwd-name">Your name</label>
                                <input class="fwd-input" id="fwd-name" name="client_name" maxlength="255" required value="{{ old('client_name') }}">
                            </div>
                            <div class="fwd-field">
                                <label class="fwd-label" for="fwd-email">Your email</label>
                                <input class="fwd-input" id="fwd-email" name="client_email" type="email" maxlength="255" required value="{{ old('client_email') }}">
                            </div>
                            <div class="fwd-field">
                                <label class="fwd-label" for="fwd-phone">Phone (optional)</label>
                                <input class="fwd-input" id="fwd-phone" name="client_phone" type="tel" maxlength="20" value="{{ old('client_phone') }}">
                            </div>
                            <div class="fwd-field full">
                                <label class="fwd-label">Delivery service</label>
                                <div class="fwd-tiers">
                                    @foreach(config('fleet.delivery_tiers') as $tierKey => $tier)
                                    <label class="fwd-tier">
                                        <input type="radio" name="delivery_tier" value="{{ $tierKey }}"
                                               @checked(old('delivery_tier', 'standard') === $tierKey)>
                                        <span class="fwd-tier-body">
                                            <span class="fwd-tier-name">{{ $tier['label'] }}</span>
                                            <span class="fwd-tier-days">arrives within {{ $tier['days'] }} days</span>
                                        </span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="fwd-field full">
                                <label class="fwd-label" for="fwd-address">Delivery address</label>
                                <input class="fwd-input" id="fwd-address" name="destination_address" maxlength="500" required
                                       placeholder="Full address the goods should be delivered to" value="{{ old('destination_address') }}">
                            </div>
                            <div class="fwd-field full">
                                <label class="fwd-label" for="fwd-notes">Delivery instructions (optional)</label>
                                <textarea class="fwd-input" id="fwd-notes" name="delivery_notes" maxlength="1000" rows="2"
                                          placeholder="Unit / floor / gate code, landmark, or notes for the driver">{{ old('delivery_notes') }}</textarea>
                            </div>
                        </div>
                        <div style="margin-top:16px;">
                            <button class="search-btn" type="submit">
                                Submit Request
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                            </button>
                            <p class="fwd-fineprint">You'll be emailed if your request is approved.</p>
                        </div>
                    </form>
                </div>
            </details>
        @endif

    @elseif(! $shipment)
        {{-- Not found --}}
        <div class="not-found">
            <div class="code">404</div>
            <p style="margin-top:16px;">No shipment found for code <strong>{{ strtoupper($code) }}</strong>.</p>
            <p style="margin-top:8px; font-size:11px; color:var(--subtle);">Check the code and try again, or contact the sender.</p>
        </div>

    @else
        @php
            $badgeClass = match($shipment->status) {
                'in_transit' => 'status-transit',
                'delayed'    => 'status-delayed',
                'delivered'  => 'status-delivered',
                'cancelled'  => 'status-pending',
                default      => 'status-pending',
            };
        @endphp

        {{-- ── Status stepper: the journey at a glance ── --}}
        <div class="stepper" id="stepper">
            <div class="step" id="step-created">
                <div class="step-dot"><svg viewBox="0 0 24 24" fill="none" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                <div class="step-label">Order received</div>
                <div class="step-sub">{{ $shipment->created_at->format('d M, H:i') }}</div>
            </div>
            <div class="step" id="step-transit">
                <div class="step-dot"><svg viewBox="0 0 24 24" fill="none" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                <div class="step-label" id="step-transit-label">In transit</div>
                <div class="step-sub" id="step-transit-sub"></div>
            </div>
            <div class="step" id="step-delivered">
                <div class="step-dot"><svg viewBox="0 0 24 24" fill="none" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                <div class="step-label" id="step-delivered-label">Delivered</div>
                <div class="step-sub" id="step-delivered-sub">
                    {{ $shipment->actual_delivery_at?->format('d M, H:i') ?? '' }}
                </div>
            </div>
        </div>
        <div class="shipment-layout">

            {{-- Info panel --}}
            <div class="info-card">
                <div class="info-card-header">
                    <div class="tracking-code-label">Tracking Code</div>
                    <div class="tracking-code-value">{{ $shipment->tracking_code }}</div>
                    <div>
                        <span class="status-badge {{ $badgeClass }}" id="status-badge">
                            {{ str_replace('_', ' ', $shipment->status) }}
                        </span>
                    </div>
                </div>
                <div class="info-body">
                    <div class="info-row">
                        <span class="info-row-label">Recipient</span>
                        <span class="info-row-value">{{ $shipment->client_name }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">From</span>
                        <span class="info-row-value">{{ $shipment->origin_address }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">To</span>
                        <span class="info-row-value">{{ $shipment->destination_address }}</span>
                    </div>
                    @if($shipment->delivery_tier)
                    <div class="info-row">
                        <span class="info-row-label">Service</span>
                        <span class="info-row-value">
                            {{ config("fleet.delivery_tiers.{$shipment->delivery_tier}.label") ?? ucfirst($shipment->delivery_tier) }}
                        </span>
                    </div>
                    @endif
                    <div class="info-row">
                        <span class="info-row-label">Expected Delivery</span>
                        <span class="info-row-value">{{ $shipment->expected_delivery_at->format('d M Y, H:i') }}</span>
                    </div>
                    @if($shipment->actual_delivery_at)
                    <div class="info-row">
                        <span class="info-row-label">Delivered At</span>
                        <span class="info-row-value" style="color: var(--success);">
                            {{ $shipment->actual_delivery_at->format('d M Y, H:i') }}
                        </span>
                    </div>
                    @endif
                    <div class="info-row">
                        <span class="info-row-label">Vehicle Speed</span>
                        <span class="info-row-value" id="live-speed">
                            {{ $shipment->vehicle->latestPosition ? number_format($shipment->vehicle->latestPosition->speed_kmh, 1) . ' km/h' : '—' }}
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Last Updated</span>
                        <span class="info-row-value" id="live-time">
                            {{ $shipment->vehicle->latestPosition?->recorded_at?->diffForHumans() ?? 'No data' }}
                        </span>
                    </div>
                    <div class="info-row" id="eta-row" style="display:none;">
                        <span class="info-row-label">Estimated Arrival</span>
                        <span class="info-row-value" id="eta-value" style="font-weight:600;">—</span>
                    </div>
                    @if($shipment->vehicle->driver_name || $shipment->vehicle->driver_phone)
                    <div class="info-row">
                        <span class="info-row-label">Driver</span>
                        <span class="info-row-value" id="driver-name">
                            {{ $shipment->vehicle->driver_name ?? '—' }}
                        </span>
                    </div>
                    @endif
                    @if($shipment->vehicle->driver_phone)
                    <div class="info-row">
                        <span class="info-row-label">Driver Contact</span>
                        <span class="info-row-value">
                            <a href="tel:{{ $shipment->vehicle->driver_phone }}"
                                id="driver-phone"
                                style="color:var(--accent); text-decoration:none; font-weight:600;">
                                {{ $shipment->vehicle->driver_phone }}
                            </a>
                        </span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Map --}}
            <div>
                <div class="map-card">
                    <div class="map-card-header">
                        <span class="map-card-title" id="map-title">Live Location</span>
                        <span style="flex:1"></span>
                        <span class="conn-pill" id="conn-pill">Reconnecting&hellip;</span>
                        <span class="live-dot" id="live-indicator">LIVE</span>
                    </div>
                    <div class="map-wrap">
                        <div id="client-map"></div>
                    </div>
                </div>
                <div class="privacy-note">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    <span>For privacy, the vehicle's live position is shown only while your shipment is on the way — not before dispatch or after delivery.</span>
                </div>
            </div>

        </div>

    @endif

</div>

<div class="footer">FleetTrack Shipment Tracker</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>


@if($shipment)
<script>
const TRACKING_CODE = @json($shipment->tracking_code);
const DEST_LAT = {{ $shipment->destination_lat }};
const DEST_LNG = {{ $shipment->destination_lng }};

@php
    // Compute these in PHP so the @json directives below receive simple
    // variables — Blade can choke on in_array(..., [...]) inside @json().
    // delayed = late but never started, so it counts as "not yet dispatched".
    $locationHidden     = $shipment->status !== 'in_transit';
    $isTerminal         = in_array($shipment->status, ['delivered', 'cancelled']);
    $isPending          = in_array($shipment->status, ['pending', 'delayed']);
    $deliveredAtIso     = $shipment->actual_delivery_at?->toIso8601String();
    $showInitialVehicle = $shipment->vehicle->latestPosition && ! $locationHidden;
@endphp
// The truck location is shown only while moving (in_transit). It's hidden
// before dispatch (pending / delayed-unstarted) and after completion
// (delivered/cancelled).
const LOCATION_HIDDEN = @json($locationHidden);
const IS_TERMINAL     = @json($isTerminal);
const IS_PENDING      = @json($isPending);
const IS_CANCELLED    = @json($shipment->status === 'cancelled');
const INITIAL_STATUS  = @json($shipment->status);
const DELIVERED_AT    = @json($deliveredAtIso);

const map = L.map('client-map').setView(
    LOCATION_HIDDEN
        ? [DEST_LAT, DEST_LNG]
        : [{{ $shipment->vehicle->latestPosition?->latitude ?? $shipment->destination_lat }},
           {{ $shipment->vehicle->latestPosition?->longitude ?? $shipment->destination_lng }}],
    LOCATION_HIDDEN ? 14 : 13
);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

// Destination marker
L.marker([DEST_LAT, DEST_LNG], {
    icon: L.divIcon({
        className: '',
        html: `<div style="
            width:14px; height:14px; border-radius:50%;
            background:#dc2626; border:3px solid #fff;
            box-shadow:0 2px 8px rgba(220,38,38,0.5);
        "></div>`,
        iconSize: [14, 14], iconAnchor: [7, 7],
    })
}).addTo(map).bindPopup('<b>Destination</b><br>{{ $shipment->destination_address }}');

// Vehicle marker
const vehicleIcon = L.divIcon({
    className: '',
    html: `<div style="width:16px;height:16px;border-radius:50%;background:#ff6b35;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.3);"></div>`,
    iconSize: [16, 16], iconAnchor: [8, 8],
});

@if($showInitialVehicle)
let vehicleMarker = L.marker(
    [{{ $shipment->vehicle->latestPosition->latitude }}, {{ $shipment->vehicle->latestPosition->longitude }}],
    { icon: vehicleIcon }
).addTo(map).bindPopup('Your shipment is here');
@else
let vehicleMarker = null;
@endif

// Road route line (truck → destination). The geometry is computed server-side
// by OSRM and arrives in the status payload as [lat,lng] pairs, so the browser
// never needs to reach the routing engine. Drawn only while the shipment moves;
// cleared on pending/delivered/cancelled alongside the truck marker.
let routeLine   = null;
let routeFitted = false;

function drawRoute(geometry) {
    if (!geometry || geometry.length < 2) { clearRoute(); return; }
    if (routeLine) {
        // routeLine is a LayerGroup (casing + line); update both polylines.
        routeLine.eachLayer(l => l.setLatLngs(geometry));
    } else {
        // White casing underneath a blue line for legibility over map tiles.
        routeLine = L.layerGroup([
            L.polyline(geometry, { color: '#ffffff', weight: 8, opacity: 0.9, lineJoin: 'round', lineCap: 'round', smoothFactor: 0.5 }),
            L.polyline(geometry, { color: '#2563eb', weight: 4, opacity: 0.85, lineJoin: 'round', lineCap: 'round', smoothFactor: 0.5 }),
        ]).addTo(map);
    }
    // Frame the whole journey once, the first time we have a route, then leave
    // the view alone so the user can pan/zoom freely. The line spans the truck's
    // current position to the destination, so it stays within these bounds as
    // the truck approaches.
    if (!routeFitted) {
        map.fitBounds(L.latLngBounds(geometry), { padding: [40, 40] });
        routeFitted = true;
    }
}

function clearRoute() {
    if (routeLine) { map.removeLayer(routeLine); routeLine = null; }
}

// ── Status stepper ────────────────────────────────────────────────────────
function updateStepper(status) {
    const created   = document.getElementById('step-created');
    const transit   = document.getElementById('step-transit');
    const delivered = document.getElementById('step-delivered');
    if (!created) return;

    created.className   = 'step done';
    transit.className   = 'step';
    delivered.className = 'step';
    document.getElementById('step-transit-label').textContent   = 'In transit';
    document.getElementById('step-delivered-label').textContent = 'Delivered';

    if (status === 'in_transit') {
        transit.className = 'step current';
        document.getElementById('step-transit-sub').textContent = 'on the way';
    } else if (status === 'delayed') {
        transit.className = 'step current warn';
        document.getElementById('step-transit-label').textContent = 'In transit (delayed)';
        document.getElementById('step-transit-sub').textContent   = 'running late';
    } else if (status === 'delivered') {
        transit.className   = 'step done';
        delivered.className = 'step done';
        document.getElementById('step-transit-sub').textContent = '';
    } else if (status === 'cancelled') {
        delivered.className = 'step cancel';
        document.getElementById('step-delivered-label').textContent = 'Cancelled';
    } else {
        // pending — nothing beyond "order received"
        document.getElementById('step-transit-sub').textContent = 'awaiting dispatch';
    }
}

// ── Connection / throttle resilience ─────────────────────────────────────
// On repeated failures show "Reconnecting". On HTTP 429 (rate limited),
// back off: skip the next few polls instead of hammering the server.
let connFails   = 0;
let backoffSkip = 0;

function setConn(ok) {
    connFails = ok ? 0 : connFails + 1;
    const pill = document.getElementById('conn-pill');
    if (pill) pill.style.display = connFails >= 2 ? 'inline-flex' : 'none';
}

async function pollStatus() {
    if (backoffSkip > 0) { backoffSkip--; return; }
    try {
        const res = await fetch(`/api/track/${TRACKING_CODE}/status`, {
            headers: { 'Accept': 'application/json' }
        });

        if (res.status === 429) {
            // Rate limited — wait ~3 cycles before trying again.
            backoffSkip = 3;
            setConn(false);
            return;
        }
        if (!res.ok) { setConn(false); return; }

        const data = await res.json();
        setConn(true);

        if (data.status === 'delivered' || data.status === 'cancelled') {
            // Terminal — stop tracking and switch the card out of live mode.
            applyDeliveredState(data);
        } else if (data.location_hidden) {
            // Pending (not yet dispatched) — keep polling, but reveal no truck yet.
            applyPendingState();
        } else if (data.vehicle) {
            // Moving — ensure the card is in live mode (in case it was pending).
            restoreLiveState();

            const latlng = [data.vehicle.latitude, data.vehicle.longitude];
            if (vehicleMarker) {
                vehicleMarker.setLatLng(latlng);
            } else {
                vehicleMarker = L.marker(latlng, { icon: vehicleIcon }).addTo(map).bindPopup('Your shipment is here');
            }

            // Road route from the truck to the destination (OSRM geometry, server-side).
            // When it's available we keep the whole route framed; if OSRM is down
            // (no geometry) we fall back to gently following the truck.
            if (data.eta && data.eta.geometry && data.eta.geometry.length > 1) {
                drawRoute(data.eta.geometry);
            } else {
                clearRoute();
                map.panTo(latlng);
            }

            document.getElementById('live-speed').textContent = (data.vehicle.speed_kmh?.toFixed(1) ?? 0) + ' km/h';
            document.getElementById('live-time').textContent  = timeAgo(data.vehicle.recorded_at);

            // Road ETA from OSRM (computed server-side), shown only while moving.
            const etaRow = document.getElementById('eta-row');
            if (data.eta && etaRow) {
                document.getElementById('eta-value').textContent =
                    '~' + data.eta.eta_minutes + ' min \u00b7 ' + data.eta.distance_km + ' km';
                etaRow.style.display = '';
            } else if (etaRow) {
                etaRow.style.display = 'none';
            }

            // Update driver info if elements exist
            const driverName  = document.getElementById('driver-name');
            const driverPhone = document.getElementById('driver-phone');
            if (driverName  && data.vehicle.driver_name)  driverName.textContent  = data.vehicle.driver_name;
            if (driverPhone && data.vehicle.driver_phone) {
                driverPhone.textContent = data.vehicle.driver_phone;
                driverPhone.href        = 'tel:' + data.vehicle.driver_phone;
            }
        }

        // Update status badge — className swap keeps the ::before dot
        const badge    = document.getElementById('status-badge');
        const classMap = { 'in_transit':'status-transit', 'delayed':'status-delayed', 'delivered':'status-delivered', 'pending':'status-pending', 'cancelled':'status-pending' };
        badge.className = 'status-badge ' + (classMap[data.status] ?? 'status-pending');
        badge.textContent = data.status.replace(/_/g, ' ');

        updateStepper(data.status);

    } catch(e) { setConn(false); console.error(e); }
}

function timeAgo(iso) {
    if (!iso) return 'Unknown';
    const diff = Math.floor((Date.now() - new Date(iso)) / 1000);
    if (diff < 60)   return diff + 's ago';
    if (diff < 3600) return Math.floor(diff/60) + 'm ago';
    return Math.floor(diff/3600) + 'h ago';
}

// When a delivery completes, drop the live truck marker and switch the card
// out of "live" mode. The server already withholds the coordinates; this just
// mirrors that in the UI and stops further polling.
let deliveredHandled = false;
function applyDeliveredState(data) {
    if (vehicleMarker) { map.removeLayer(vehicleMarker); vehicleMarker = null; }
    clearRoute();

    const cancelled = (data && data.status === 'cancelled') || IS_CANCELLED;

    const liveDot = document.getElementById('live-indicator');
    if (liveDot) liveDot.style.display = 'none';
    const connPill = document.getElementById('conn-pill');
    if (connPill) connPill.style.display = 'none';

    const title = document.getElementById('map-title');
    if (title) title.textContent = cancelled ? 'Tracking Ended' : 'Delivery Location';
    const etaRow = document.getElementById('eta-row');
    if (etaRow) etaRow.style.display = 'none';

    const speedEl = document.getElementById('live-speed');
    if (speedEl) speedEl.textContent = '—';

    const timeEl = document.getElementById('live-time');
    if (timeEl) timeEl.textContent = (!cancelled && data && data.delivered_at)
        ? ('Delivered ' + timeAgo(data.delivered_at))
        : 'Tracking ended';

    map.setView([DEST_LAT, DEST_LNG], 14);
    updateStepper(cancelled ? 'cancelled' : 'delivered');

    if (!deliveredHandled) {
        deliveredHandled = true;
        if (pollTimer) clearInterval(pollTimer);
    }
}

let pollTimer = null;

// Pending: the shipment exists but the driver hasn't started the trip, so no
// truck location is exposed. Unlike the delivered state, polling keeps running
// so the map comes alive the instant the driver dispatches.
function applyPendingState() {
    if (vehicleMarker) { map.removeLayer(vehicleMarker); vehicleMarker = null; }
    clearRoute();
    routeFitted = false;
    const liveDot = document.getElementById('live-indicator');
    if (liveDot) liveDot.style.display = 'none';
    const title = document.getElementById('map-title');
    if (title) title.textContent = 'Awaiting Dispatch';
    const speedEl = document.getElementById('live-speed');
    if (speedEl) speedEl.textContent = '—';
    const timeEl = document.getElementById('live-time');
    if (timeEl) timeEl.textContent = 'Not yet on the way';
    map.setView([DEST_LAT, DEST_LNG], 14);
    updateStepper('pending');
}

// Restore the live presentation when a shipment moves from pending to in transit.
function restoreLiveState() {
    const liveDot = document.getElementById('live-indicator');
    if (liveDot) liveDot.style.display = '';
    const title = document.getElementById('map-title');
    if (title) title.textContent = 'Live Location';
}

updateStepper(INITIAL_STATUS);

if (IS_TERMINAL) {
    // Already delivered/cancelled on page load — never reveal the truck, stop here.
    applyDeliveredState({ delivered_at: DELIVERED_AT, status: INITIAL_STATUS });
} else {
    // Pending or moving — keep polling. Pending shows the awaiting-dispatch state
    // and flips to live automatically once the driver starts.
    if (IS_PENDING) applyPendingState();
    pollTimer = setInterval(pollStatus, 10000); // poll every 10s
    pollStatus();
}
</script>
@endif

</body>
</html>
