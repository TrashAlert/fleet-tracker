<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — FleetTrack</title>
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
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }

        /* Subtle grid background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(var(--border) 1px, transparent 1px),
                linear-gradient(90deg, var(--border) 1px, transparent 1px);
            background-size: 40px 40px;
            opacity: 0.3;
            pointer-events: none;
        }

        /* Accent glow blob */
        body::after {
            content: '';
            position: fixed;
            top: -200px;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 400px;
            background: radial-gradient(ellipse, rgba(0,229,255,0.06) 0%, transparent 70%);
            pointer-events: none;
        }

        .login-wrap {
            width: 100%;
            max-width: 400px;
            padding: 24px;
            position: relative;
            z-index: 1;
        }

        /* Logo */
        .logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo-wordmark {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 28px;
            color: var(--accent);
            letter-spacing: -1px;
        }
        .logo-tagline {
            font-size: 10px;
            color: var(--subtle);
            letter-spacing: 0.2em;
            text-transform: uppercase;
            margin-top: 4px;
        }

        /* Card */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 32px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.4);
        }

        .card-title {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 18px;
            color: var(--text);
            margin-bottom: 6px;
        }
        .card-subtitle {
            font-size: 11px;
            color: var(--subtle);
            margin-bottom: 28px;
        }

        /* Form */
        .field { margin-bottom: 18px; }
        .field label {
            display: block;
            font-size: 10px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--subtle);
            margin-bottom: 7px;
        }
        .field input {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 11px 14px;
            font-family: var(--font-mono);
            font-size: 13px;
            color: var(--text);
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .field input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0,229,255,0.08);
        }
        .field input::placeholder { color: var(--muted); }

        /* Error state */
        .field.has-error input {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(239,68,68,0.08);
        }
        .field-error {
            font-size: 11px;
            color: var(--danger);
            margin-top: 5px;
        }

        /* Global error alert */
        .alert-error {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.25);
            border-radius: 8px;
            padding: 11px 14px;
            font-size: 12px;
            color: var(--danger);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Remember me */
        .remember {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .remember label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--subtle);
            cursor: pointer;
        }
        .remember input[type="checkbox"] {
            accent-color: var(--accent);
            width: 14px;
            height: 14px;
        }

        /* Submit button */
        .btn-login {
            width: 100%;
            padding: 13px;
            background: var(--accent);
            color: #000;
            border: none;
            border-radius: 8px;
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            letter-spacing: 0.03em;
            transition: opacity 0.15s, transform 0.1s;
        }
        .btn-login:hover  { opacity: 0.88; }
        .btn-login:active { transform: scale(0.99); }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 10px;
            color: var(--subtle);
            letter-spacing: 0.05em;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: var(--muted); border-radius: 3px; }
    </style>
</head>
<body>

<div class="login-wrap">

    <div class="logo">
        <div class="logo-wordmark">FleetTrack</div>
        <div class="logo-tagline">GPS Control System</div>
    </div>

    <div class="card">
        <div class="card-title">Sign In</div>
        <div class="card-subtitle">Internal access only. Authorised personnel only.</div>

        {{-- Error alert --}}
        @if ($errors->any())
        <div class="alert-error">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12" y2="16.5" stroke-width="3"/>
            </svg>
            {{ $errors->first() }}
        </div>
        @endif

        <form method="POST" action="{{ route('auth.login.post') }}">
            @csrf

            <div class="field {{ $errors->has('email') ? 'has-error' : '' }}">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="you@fleettrack.local"
                    autocomplete="email"
                    autofocus
                    required
                >
            </div>

            <div class="field {{ $errors->has('password') ? 'has-error' : '' }}">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="remember">
                <label>
                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                    Remember me
                </label>
            </div>

            <button type="submit" class="btn-login">Sign In →</button>
        </form>
    </div>

    <div class="login-footer">
        FleetTrack &nbsp;·&nbsp; No unauthorised access &nbsp;·&nbsp; All activity is logged
    </div>
</div>

</body>
</html>
