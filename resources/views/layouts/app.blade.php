<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Fleet Control') — FleetTrack</title>

    {{-- Leaflet CSS --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

    {{-- Fonts: Syne (display) + JetBrains Mono (data) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg:        #0c0d0f;
            --surface:   #13151a;
            --border:    #1f2330;
            --muted:     #2e3347;
            --text:      #e8eaf0;
            --subtle:    #6b7280;
            --accent:    #00e5ff;
            --accent2:   #ff6b35;
            --success:   #22c55e;
            --warning:   #f59e0b;
            --danger:    #ef4444;
            --font-display: 'Syne', sans-serif;
            --font-mono:    'JetBrains Mono', monospace;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-mono);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            font-size: 13px;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: 220px;
            min-height: 100vh;
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
        }

        .sidebar-logo {
            padding: 24px 20px 20px;
            border-bottom: 1px solid var(--border);
        }
        .sidebar-logo .wordmark {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 18px;
            color: var(--accent);
            letter-spacing: -0.5px;
        }
        .sidebar-logo .tagline {
            font-size: 10px;
            color: var(--subtle);
            margin-top: 2px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .sidebar-nav {
            padding: 16px 12px;
            flex: 1;
        }
        .nav-label {
            font-size: 9px;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--subtle);
            padding: 0 8px;
            margin: 16px 0 6px;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: 6px;
            color: var(--subtle);
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.15s;
            margin-bottom: 2px;
        }
        .nav-item:hover, .nav-item.active {
            background: var(--muted);
            color: var(--text);
        }
        .nav-item.active { color: var(--accent); }
        .nav-item svg { width: 14px; height: 14px; flex-shrink: 0; }

        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            font-size: 11px;
            color: var(--subtle);
        }

        /* ── Main content ── */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .topbar {
            height: 52px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            background: var(--surface);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .topbar-title {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 15px;
            color: var(--text);
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        /* Alert badge */
        .alert-badge {
            position: relative;
            cursor: pointer;
            color: var(--subtle);
        }
        .alert-badge .dot {
            position: absolute;
            top: -3px; right: -3px;
            width: 8px; height: 8px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid var(--surface);
            display: none;
        }
        .alert-badge.has-alerts .dot { display: block; }

        .content { padding: 24px; flex: 1; }

        /* ── Cards ── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
        }
        .card-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-title {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 13px;
            color: var(--text);
            letter-spacing: 0.02em;
        }
        .card-body { padding: 18px; }

        /* ── Stat tiles ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }
        .stat-tile {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px;
        }
        .stat-label {
            font-size: 10px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--subtle);
            margin-bottom: 8px;
        }
        .stat-value {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
            color: var(--text);
        }
        .stat-value.accent  { color: var(--accent); }
        .stat-value.warning { color: var(--warning); }
        .stat-value.danger  { color: var(--danger); }
        .stat-value.success { color: var(--success); }

        /* ── Status pills ── */
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .pill::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
        .pill-online  { background: #16301e; color: var(--success); }
        .pill-offline { background: #2a1a1a; color: var(--danger); }
        .pill-transit { background: #1a2a3a; color: var(--accent); }
        .pill-delayed { background: #2a2010; color: var(--warning); }
        .pill-delivered { background: #16301e; color: var(--success); }

        /* ── Table ── */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            text-align: left;
            font-size: 10px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--subtle);
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
        }
        .data-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            font-size: 12px;
            color: var(--text);
        }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: rgba(255,255,255,0.02); }

        /* ── Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 6px;
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.15s;
        }
        .btn-primary {
            background: var(--accent);
            color: #000;
        }
        .btn-primary:hover { opacity: 0.85; }
        .btn-ghost {
            background: transparent;
            color: var(--subtle);
            border: 1px solid var(--border);
        }
        .btn-ghost:hover { color: var(--text); border-color: var(--muted); }

        /* ── Map ── */
        #fleet-map {
            height: 100%;
            min-height: 480px;
            border-radius: 0;
            background: #0a0b0e;
        }

        /* ── Misc ── */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .grid-3 { display: grid; grid-template-columns: 2fr 1fr; gap: 14px; }
        .mono { font-family: var(--font-mono); }
        .text-accent  { color: var(--accent); }
        .text-subtle  { color: var(--subtle); }
        .text-danger  { color: var(--danger); }
        .text-success { color: var(--success); }
        .text-warning { color: var(--warning); }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--muted); border-radius: 3px; }

        /* Alerts panel */
        .alert-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .alert-item:last-child { border-bottom: none; }
        .alert-icon {
            width: 30px; height: 30px;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            font-size: 13px;
        }
        .alert-icon.overspeed { background: #2a1010; }
        .alert-icon.delay     { background: #2a2010; }
        .alert-icon.offline   { background: #1a1a2a; }
        .alert-msg { font-size: 11px; color: var(--text); line-height: 1.5; }
        .alert-time { font-size: 10px; color: var(--subtle); margin-top: 3px; }

        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .grid-3 { grid-template-columns: 1fr; }
        }

        /* ── Mobile top bar (hidden on desktop) ── */
        .mobile-topbar {
            display: none;
            position: sticky;
            top: 0;
            z-index: 300;
            height: 52px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
        }
        .mobile-topbar .wordmark {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 16px;
            color: var(--accent);
        }
        .hamburger {
            background: none;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 7px 9px;
            color: var(--text);
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        /* ── Sidebar backdrop for mobile drawer ── */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.55);
            z-index: 399;
        }

        /* ── Bottom nav (driver mobile only) ── */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0; left: 0; right: 0;
            z-index: 300;
            background: var(--surface);
            border-top: 1px solid var(--border);
            padding: 6px 0 calc(6px + env(safe-area-inset-bottom));
        }
        .bottom-nav-inner {
            display: flex;
            justify-content: space-around;
            align-items: center;
        }
        .bottom-nav a, .bottom-nav button {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            background: none;
            border: none;
            color: var(--subtle);
            text-decoration: none;
            font-family: var(--font-mono);
            font-size: 9px;
            letter-spacing: .05em;
            text-transform: uppercase;
            padding: 6px 18px;
            cursor: pointer;
            border-radius: 8px;
        }
        .bottom-nav a.active { color: var(--accent); }
        .bottom-nav svg { width: 20px; height: 20px; }

        /* ── Mobile layout ── */
        @media (max-width: 768px) {
            body { flex-direction: column; }

            .mobile-topbar { display: flex; }

            /* Sidebar becomes a slide-in drawer */
            .sidebar {
                position: fixed;
                top: 0; left: 0;
                height: 100vh;
                width: 260px;
                z-index: 400;
                transform: translateX(-100%);
                transition: transform .25s cubic-bezier(.4,0,.2,1);
                box-shadow: 8px 0 32px rgba(0,0,0,.5);
            }
            .sidebar.open { transform: translateX(0); }
            .sidebar-backdrop.show { display: block; }

            /* Hide desktop topbar on mobile (mobile-topbar replaces it) */
            .topbar { display: none; }

            .content { padding: 14px; }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            .stat-tile { padding: 13px; }
            .stat-value { font-size: 22px; }

            .grid-2, .grid-3 { grid-template-columns: 1fr; gap: 12px; }

            /* Tables scroll horizontally instead of squishing */
            .data-table { min-width: 640px; }
            .card > div[style*="overflow-x"] { -webkit-overflow-scrolling: touch; }

            /* Bigger touch targets */
            .btn { padding: 10px 16px; }

            /* Bottom nav visible for drivers — body padding so content isn't hidden */
            body.has-bottom-nav .bottom-nav { display: block; }
            body.has-bottom-nav .content { padding-bottom: 76px; }
            body.has-bottom-nav .mobile-topbar .hamburger { display: none; }
        }
    </style>
    @stack('styles')
</head>
<body class="{{ auth()->user()?->isDriver() ? 'has-bottom-nav' : '' }}">

{{-- Mobile top bar (mobile only) --}}
<header class="mobile-topbar">
    <div class="wordmark">FleetTrack</div>
    <button class="hamburger" onclick="toggleSidebar()" aria-label="Menu">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
    </button>
</header>

{{-- Backdrop for mobile drawer --}}
<div class="sidebar-backdrop" id="sidebar-backdrop" onclick="toggleSidebar()"></div>

{{-- Sidebar --}}
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="wordmark">FleetTrack</div>
        <div class="tagline">GPS Control System</div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Monitor</div>
        <a href="{{ route('fleet.dashboard') }}" class="nav-item {{ request()->routeIs('fleet.dashboard') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
            Dashboard
        </a>
        @if(!auth()->user()->isDriver())
        <a href="{{ route('fleet.vehicles') }}" class="nav-item {{ request()->routeIs('fleet.vehicles') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2h-2"/><circle cx="9" cy="20" r="2"/><circle cx="17" cy="20" r="2"/></svg>
            Vehicles
        </a>
        @endif
        <div class="nav-label">Logistics</div>
        <a href="{{ route('fleet.shipments') }}" class="nav-item {{ request()->routeIs('fleet.shipments') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            {{ auth()->user()->isDriver() ? 'My Shipments' : 'Shipments' }}
        </a>
        @if(!auth()->user()->isDriver())
        <div class="nav-label">Configuration</div>
        <a href="{{ route('fleet.origins') }}" class="nav-item {{ request()->routeIs('fleet.origins') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
            Origin Locations
        </a>
        @endif
        @if(!auth()->user()->isDriver())
        <div class="nav-label">System</div>
        <a href="{{ route('fleet.activity-log') }}" class="nav-item {{ request()->routeIs('fleet.activity-log') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Activity Log
        </a>
        @if(auth()->user()?->isAdmin())
        <a href="{{ route('fleet.users') }}" class="nav-item {{ request()->routeIs('fleet.users') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            Users
        </a>
        @endif
        @endif
    </nav>
    <div class="sidebar-footer">
        <div style="margin-bottom:10px;">
            <div style="color:var(--text); font-size:12px; font-weight:600;">{{ auth()->user()?->name ?? '—' }}</div>
            <div style="font-size:10px; margin-top:2px;">
                <span style="color:{{ auth()->user()?->getRoleBadgeColor() ?? 'var(--subtle)' }};">
                    {{ strtoupper(auth()->user()?->role ?? '') }}
                </span>
                &nbsp;·&nbsp;
                <span style="color: var(--accent);">● LIVE</span>
            </div>
        </div>
        <form method="POST" action="{{ route('auth.logout') }}">
            @csrf
            <button type="submit" style="
                width:100%; background:transparent; border:1px solid var(--border);
                border-radius:6px; padding:7px 10px; color:var(--subtle);
                font-family:var(--font-mono); font-size:11px; cursor:pointer;
                transition:all .15s; text-align:left;
            " onmouseover="this.style.color='var(--danger)';this.style.borderColor='var(--danger)';"
               onmouseout="this.style.color='var(--subtle)';this.style.borderColor='var(--border)';">
                ⎋ &nbsp;Sign Out
            </button>
        </form>
    </div>
</aside>

{{-- Main --}}
<div class="main">
    <header class="topbar">
        <span class="topbar-title">@yield('title', 'Dashboard')</span>
        <div class="topbar-right">
            <div class="alert-badge" id="alertBadge">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                <span class="dot"></span>
            </div>
        </div>
    </header>

    <div class="content">
        @yield('content')
    </div>
</div>

{{-- Bottom nav — driver mobile only --}}
@if(auth()->user()?->isDriver())
<nav class="bottom-nav">
    <div class="bottom-nav-inner">
        <a href="{{ route('fleet.dashboard') }}" class="{{ request()->routeIs('fleet.dashboard') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
            Dashboard
        </a>
        <a href="{{ route('fleet.shipments') }}" class="{{ request()->routeIs('fleet.shipments') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Deliveries
        </a>
        <button onclick="document.getElementById('mobile-logout-form').submit()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sign Out
        </button>
    </div>
</nav>
<form id="mobile-logout-form" method="POST" action="{{ route('auth.logout') }}" style="display:none;">
    @csrf
</form>
@endif

{{-- Sidebar drawer toggle --}}
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-backdrop').classList.toggle('show');
}
// Close drawer when a nav link is tapped
document.querySelectorAll('.sidebar .nav-item').forEach(link => {
    link.addEventListener('click', () => {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebar-backdrop').classList.remove('show');
    });
});
</script>

{{-- Leaflet JS --}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

@stack('scripts')
</body>
</html>
